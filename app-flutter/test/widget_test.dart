import 'package:chronus_access/features/auth/login_page.dart';
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
}
