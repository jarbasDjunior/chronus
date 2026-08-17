import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'local_database.dart';

class ApiClient {
  ApiClient(this.local)
    : dio = Dio(
        BaseOptions(
          baseUrl: const String.fromEnvironment(
            'API_URL',
            defaultValue: 'http://10.0.2.2:8000/api/v1',
          ),
          connectTimeout: const Duration(seconds: 8),
          receiveTimeout: const Duration(seconds: 15),
          headers: {'Accept': 'application/json'},
        ),
      ) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (o, h) async {
          final t = await storage.read(key: 'token');
          if (t != null) o.headers['Authorization'] = 'Bearer $t';
          h.next(o);
        },
      ),
    );
  }
  final Dio dio;
  final LocalDatabase local;
  static const storage = FlutterSecureStorage();
  Future<Map<String, dynamic>> login(String login, String password) async {
    final r = await dio.post(
      '/auth/login',
      data: {
        'login': login,
        'password': password,
        'device_name': 'Chronus App',
      },
    );
    final data = Map<String, dynamic>.from(r.data['data']);
    await storage.write(key: 'token', value: data['token']);
    await storage.write(key: 'user', value: jsonEncode(data['user']));
    return Map<String, dynamic>.from(data['user']);
  }

  Future<Map<String, dynamic>?> restore() async {
    final raw = await storage.read(key: 'user');
    final token = await storage.read(key: 'token');
    if (raw == null || token == null) {
      await storage.deleteAll();
      return null;
    }

    late final Map<String, dynamic> cachedUser;
    try {
      cachedUser = Map<String, dynamic>.from(jsonDecode(raw));
    } catch (_) {
      await storage.deleteAll();
      return null;
    }

    try {
      final response = await dio.get('/auth/me');
      final user = Map<String, dynamic>.from(response.data['data']);
      await storage.write(key: 'user', value: jsonEncode(user));
      return user;
    } on DioException catch (error) {
      if (error.response?.statusCode == 401 ||
          error.response?.statusCode == 403) {
        await storage.deleteAll();
        return null;
      }
      return cachedUser;
    }
  }

  Future<void> logout() async {
    try {
      await dio.post('/auth/logout');
    } finally {
      await storage.deleteAll();
    }
  }

  Future<Map<String, dynamic>> dashboard() async =>
      Map<String, dynamic>.from((await dio.get('/dashboard')).data['data']);

  Future<Map<String, dynamic>?> currentGatekeeperShift() async {
    final data = (await dio.get('/gatekeeper-shifts/current')).data['data'];
    return data == null ? null : Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> gatekeeperShiftAction(String action) async {
    final data = action == 'start'
        ? {'access_location_id': 1}
        : <String, dynamic>{};
    final response = await dio.post('/gatekeeper-shifts/$action', data: data);
    return Map<String, dynamic>.from(response.data['data']);
  }

  Future<List<dynamic>> resource(String name, {String search = ''}) async {
    final response = await dio.get(
      '/$name',
      queryParameters: {'search': search, 'per_page': 100},
    );
    return response.data['data'] as List;
  }

  Future<Map<String, dynamic>> saveResource(
    String name,
    Map<String, dynamic> payload, {
    int? id,
  }) async {
    final response = id == null
        ? await dio.post('/$name', data: payload)
        : await dio.put('/$name/$id', data: payload);
    return Map<String, dynamic>.from(response.data['data']);
  }

  Future<List<dynamic>> people(String search) async {
    try {
      final data = (await dio.get(
        '/people',
        queryParameters: {'search': search, 'per_page': 100},
      )).data;
      await local.cache('people', data['data']);
      return data['data'];
    } catch (_) {
      return (await local.cached('people') as List?) ?? [];
    }
  }

  Future<Map<String, dynamic>> person(int id) async =>
      Map<String, dynamic>.from((await dio.get('/people/$id')).data['data']);

  Future<Map<String, dynamic>> saveVehicle({
    int? id,
    required int personId,
    required String plate,
    required String model,
    required String color,
    required bool active,
  }) async {
    final payload = {
      'plate': plate,
      'model': model,
      'color': color,
      'active': active,
      'person_ids': [personId],
    };
    final response = id == null
        ? await dio.post('/vehicles', data: payload)
        : await dio.put('/vehicles/$id', data: payload);
    return Map<String, dynamic>.from(response.data['data']);
  }

  Future<bool> movement(String kind, Map<String, dynamic> payload) async {
    try {
      await dio.post('/movements/$kind', data: payload);
      return true;
    } catch (e) {
      if (e is DioException && e.response != null) rethrow;
      await local.queue(payload['uuid'], kind, {
        ...payload,
        'origin': 'offline',
      });
      return false;
    }
  }

  Future<int> sync() async {
    final rows = await local.pending();
    if (rows.isEmpty) return 0;
    try {
      final items = rows
          .map(
            (r) => {
              'kind': r['kind'],
              'payload': jsonDecode(r['payload'] as String),
            },
          )
          .toList();
      final result =
          (await dio.post('/sync', data: {'movements': items})).data['data']
              as List;
      for (final item in result) {
        if (item['status'] == 'synced') {
          await local.synced(item['uuid']);
        } else if (item['uuid'] != null) {
          await local.failed(item['uuid'], item['message'] ?? 'Erro');
        }
      }
      return await local.pendingCount();
    } catch (_) {
      return rows.length;
    }
  }
}
