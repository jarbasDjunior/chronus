import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'api_client.dart';
import 'local_database.dart';

final localProvider = Provider((_) => LocalDatabase());
final apiProvider = Provider((ref) => ApiClient(ref.read(localProvider)));
final sessionProvider =
    AsyncNotifierProvider<SessionController, Map<String, dynamic>?>(
      SessionController.new,
    );

class SessionController extends AsyncNotifier<Map<String, dynamic>?> {
  @override
  Future<Map<String, dynamic>?> build() => ref.read(apiProvider).restore();
  Future<void> login(String login, String password) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(
      () => ref.read(apiProvider).login(login, password),
    );
  }

  Future<void> logout() async {
    await ref.read(apiProvider).logout();
    state = const AsyncData(null);
  }
}
