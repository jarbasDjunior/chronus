import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:path/path.dart';
import 'package:path_provider/path_provider.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

class LocalDatabase {
  Database? _db;
  Future<Database> get db async {
    if (_db != null) return _db!;
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.windows) {
      sqfliteFfiInit();
      databaseFactory = databaseFactoryFfi;
    }
    final dir = await getApplicationSupportDirectory();
    _db = await openDatabase(
      join(dir.path, 'chronus.db'),
      version: 1,
      onCreate: (d, _) async {
        await d.execute(
          'CREATE TABLE pending_movements (uuid TEXT PRIMARY KEY, kind TEXT NOT NULL, payload TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending", error TEXT, created_at TEXT NOT NULL)',
        );
        await d.execute(
          'CREATE TABLE cache (key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL)',
        );
      },
    );
    return _db!;
  }

  Future<void> queue(
    String uuid,
    String kind,
    Map<String, dynamic> payload,
  ) async {
    final d = await db;
    await d.insert('pending_movements', {
      'uuid': uuid,
      'kind': kind,
      'payload': jsonEncode(payload),
      'created_at': DateTime.now().toUtc().toIso8601String(),
    }, conflictAlgorithm: ConflictAlgorithm.ignore);
  }

  Future<List<Map<String, dynamic>>> pending() async {
    final d = await db;
    return d.query(
      'pending_movements',
      where: 'status != ?',
      whereArgs: ['synced'],
      orderBy: 'created_at',
    );
  }

  Future<int> pendingCount() async => (await pending()).length;
  Future<void> synced(String uuid) async {
    final d = await db;
    await d.delete('pending_movements', where: 'uuid = ?', whereArgs: [uuid]);
  }

  Future<void> failed(String uuid, String error) async {
    final d = await db;
    await d.update(
      'pending_movements',
      {'status': 'error', 'error': error},
      where: 'uuid = ?',
      whereArgs: [uuid],
    );
  }

  Future<void> cache(String key, Object value) async {
    final d = await db;
    await d.insert('cache', {
      'key': key,
      'value': jsonEncode(value),
      'updated_at': DateTime.now().toUtc().toIso8601String(),
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<dynamic> cached(String key) async {
    final d = await db;
    final rows = await d.query(
      'cache',
      where: 'key = ?',
      whereArgs: [key],
      limit: 1,
    );
    return rows.isEmpty ? null : jsonDecode(rows.first['value'] as String);
  }
}
