import 'package:chronus_access/core/api_client.dart';
import 'package:chronus_access/core/local_database.dart';
import 'package:chronus_access/core/providers.dart';
import 'package:chronus_access/features/auth/login_page.dart';
import 'package:chronus_access/features/home/home_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('login responsivo exibe campos e ação', (tester) async {
    await tester.binding.setSurfaceSize(const Size(390, 844));
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: LoginPage())),
    );
    expect(find.text('Chronus'), findsOneWidget);
    expect(find.text('E-mail ou usuário'), findsOneWidget);
    expect(find.text('Entrar'), findsOneWidget);
  });

  testWidgets('painel exibe erro quando API está indisponível', (tester) async {
    await tester.binding.setSurfaceSize(const Size(390, 844));
    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiProvider.overrideWithValue(_UnavailableApiClient())],
        child: MaterialApp(
          home: Scaffold(
            body: DashboardPage(pending: 0, onSync: () {}, user: const {}),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(CircularProgressIndicator), findsNothing);
    expect(find.text('Não foi possível carregar os dados.'), findsOneWidget);
    expect(find.text('Tentar novamente'), findsOneWidget);
  });
}

class _UnavailableApiClient extends ApiClient {
  _UnavailableApiClient() : super(LocalDatabase());

  @override
  Future<Map<String, dynamic>> dashboard() =>
      Future.error(Exception('offline'));
}
