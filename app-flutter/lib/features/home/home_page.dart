import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';
import '../../core/providers.dart';
import '../people/employee_details_page.dart';

class HomePage extends ConsumerStatefulWidget {
  const HomePage({super.key});
  @override
  ConsumerState<HomePage> createState() => _HomePageState();
}

class _HomePageState extends ConsumerState<HomePage> {
  int tab = 0;
  int pending = 0;
  @override
  void initState() {
    super.initState();
    sync();
  }

  Future<void> sync() async {
    final n = await ref.read(apiProvider).sync();
    if (mounted) setState(() => pending = n);
  }

  @override
  Widget build(BuildContext context) {
    final wide = MediaQuery.sizeOf(context).width >= 800;
    final pages = [
      DashboardPage(pending: pending, onSync: sync),
      const QuickRegisterPage(),
      const PresencePage(),
      const HistoryPage(),
    ];
    final destinations = const [
      NavigationDestination(
        icon: Icon(Icons.dashboard_outlined),
        selectedIcon: Icon(Icons.dashboard),
        label: 'Início',
      ),
      NavigationDestination(
        icon: Icon(Icons.how_to_reg_outlined),
        selectedIcon: Icon(Icons.how_to_reg),
        label: 'Registrar',
      ),
      NavigationDestination(
        icon: Icon(Icons.groups_outlined),
        selectedIcon: Icon(Icons.groups),
        label: 'Presentes',
      ),
      NavigationDestination(icon: Icon(Icons.history), label: 'Histórico'),
    ];
    return Scaffold(
      appBar: AppBar(
        title: const Text('Chronus'),
        actions: [
          if (pending > 0)
            Badge(
              label: Text('$pending'),
              child: IconButton(
                onPressed: sync,
                tooltip: 'Sincronizar pendências',
                icon: const Icon(Icons.cloud_upload_outlined),
              ),
            ),
          IconButton(
            onPressed: () => ref.read(sessionProvider.notifier).logout(),
            tooltip: 'Sair',
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: Row(
        children: [
          if (wide)
            NavigationRail(
              selectedIndex: tab,
              onDestinationSelected: (v) => setState(() => tab = v),
              labelType: NavigationRailLabelType.all,
              destinations: destinations
                  .map(
                    (d) => NavigationRailDestination(
                      icon: d.icon,
                      selectedIcon: d.selectedIcon,
                      label: Text(d.label),
                    ),
                  )
                  .toList(),
            ),
          Expanded(
            child: IndexedStack(index: tab, children: pages),
          ),
        ],
      ),
      bottomNavigationBar: wide
          ? null
          : NavigationBar(
              selectedIndex: tab,
              onDestinationSelected: (v) => setState(() => tab = v),
              destinations: destinations,
            ),
    );
  }
}

class DashboardPage extends ConsumerStatefulWidget {
  const DashboardPage({super.key, required this.pending, required this.onSync});
  final int pending;
  final VoidCallback onSync;
  @override
  ConsumerState<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends ConsumerState<DashboardPage> {
  late Future<Map<String, dynamic>> future;
  @override
  void initState() {
    super.initState();
    future = ref.read(apiProvider).dashboard();
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: () async {
      setState(() => future = ref.read(apiProvider).dashboard());
      await future;
    },
    child: FutureBuilder<Map<String, dynamic>>(
      future: future,
      builder: (c, s) {
        if (!s.hasData) return const Center(child: CircularProgressIndicator());
        final d = s.data!;
        final cards = [
          (
            'Pessoas dentro',
            d['people_inside'] ?? 0,
            Icons.groups,
            Colors.teal,
          ),
          (
            'Veículos dentro',
            d['vehicles_inside'] ?? 0,
            Icons.directions_car,
            Colors.indigo,
          ),
          (
            'Movimentações hoje',
            d['movements_today'] ?? 0,
            Icons.swap_vert,
            Colors.orange,
          ),
          ('Pendentes offline', widget.pending, Icons.cloud_off, Colors.red),
        ];
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              'Visão geral',
              style: Theme.of(
                c,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: cards
                  .map(
                    (x) => SizedBox(
                      width: MediaQuery.sizeOf(c).width < 600
                          ? (MediaQuery.sizeOf(c).width - 52) / 2
                          : 220,
                      child: Card(
                        child: Padding(
                          padding: const EdgeInsets.all(18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Icon(x.$3, color: x.$4),
                              const SizedBox(height: 14),
                              Text(
                                '${x.$2}',
                                style: Theme.of(c).textTheme.headlineMedium
                                    ?.copyWith(fontWeight: FontWeight.bold),
                              ),
                              Text(x.$1),
                            ],
                          ),
                        ),
                      ),
                    ),
                  )
                  .toList(),
            ),
            const SizedBox(height: 24),
            Text(
              'Últimas movimentações',
              style: Theme.of(c).textTheme.titleLarge,
            ),
            const SizedBox(height: 8),
            ...(d['latest'] as List? ?? []).map(
              (m) => ListTile(
                contentPadding: EdgeInsets.zero,
                leading: CircleAvatar(
                  child: Icon(
                    m['type'] == 'entry' ? Icons.login : Icons.logout,
                  ),
                ),
                title: Text(m['person']?['name'] ?? ''),
                subtitle: Text(
                  '${m['location']?['name'] ?? ''} • ${m['operator']?['name'] ?? ''}',
                ),
                trailing: Text(m['type'] == 'entry' ? 'Entrada' : 'Saída'),
              ),
            ),
          ],
        );
      },
    ),
  );
}

class QuickRegisterPage extends ConsumerStatefulWidget {
  const QuickRegisterPage({super.key});
  @override
  ConsumerState<QuickRegisterPage> createState() => _QuickRegisterPageState();
}

class _QuickRegisterPageState extends ConsumerState<QuickRegisterPage> {
  final search = TextEditingController();
  List<dynamic> people = [];
  bool loading = false, busy = false;
  Future<void> find() async {
    setState(() => loading = true);
    people = await ref.read(apiProvider).people(search.text);
    if (mounted) setState(() => loading = false);
  }

  Future<void> register(Map person, String type) async {
    if (busy) return;
    final ok =
        await showDialog<bool>(
          context: context,
          builder: (c) => AlertDialog(
            title: Text(
              type == 'entry' ? 'Confirmar entrada' : 'Confirmar saída',
            ),
            content: Text(
              '${person['name']} • ${person['registration']}\nPortaria Principal',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(c, false),
                child: const Text('Cancelar'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(c, true),
                child: const Text('Confirmar'),
              ),
            ],
          ),
        ) ??
        false;
    if (!ok) return;
    setState(() => busy = true);
    try {
      final online = await ref.read(apiProvider).movement('person', {
        'uuid': const Uuid().v4(),
        'person_id': person['id'],
        'type': type,
        'access_location_id': 1,
        'occurred_at': DateTime.now().toUtc().toIso8601String(),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              online
                  ? 'Registro realizado com sucesso.'
                  : 'Sem conexão: registro salvo e pendente de sincronização.',
            ),
            backgroundColor: online ? Colors.green : Colors.orange,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Não foi possível registrar: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Text(
          'Registro rápido',
          style: Theme.of(
            context,
          ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 6),
        const Text('Pesquise por nome, matrícula ou placa.'),
        const SizedBox(height: 18),
        SearchBar(
          controller: search,
          hintText: 'Nome, matrícula ou placa',
          leading: const Icon(Icons.search),
          trailing: [
            IconButton(onPressed: find, icon: const Icon(Icons.arrow_forward)),
          ],
          onSubmitted: (_) => find(),
        ),
        if (loading) const LinearProgressIndicator(),
        const SizedBox(height: 16),
        if (!loading && people.isEmpty)
          const Padding(
            padding: EdgeInsets.all(32),
            child: Column(
              children: [
                Icon(Icons.person_search, size: 56),
                SizedBox(height: 8),
                Text('Pesquise para localizar uma pessoa ou veículo.'),
              ],
            ),
          ),
        ...people.map(
          (p) => Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                children: [
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => EmployeeDetailsPage(personId: p['id']),
                      ),
                    ),
                    leading: CircleAvatar(
                      child: Text((p['name'] as String)[0]),
                    ),
                    title: Text(p['name']),
                    subtitle: Text(
                      '${p['registration']} • ${p['category']?['name'] ?? ''}',
                    ),
                    trailing: Chip(
                      label: Text(p['current_state'] ?? 'Consultar'),
                    ),
                  ),
                  if ((p['vehicles'] as List? ?? []).isNotEmpty)
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Wrap(
                        spacing: 6,
                        children: (p['vehicles'] as List)
                            .map(
                              (v) => Chip(
                                avatar: const Icon(
                                  Icons.directions_car,
                                  size: 18,
                                ),
                                label: Text(v['plate']),
                              ),
                            )
                            .toList(),
                      ),
                    ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: busy ? null : () => register(p, 'entry'),
                          icon: const Icon(Icons.login),
                          label: const Padding(
                            padding: EdgeInsets.symmetric(vertical: 12),
                            child: Text('Entrada'),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: busy ? null : () => register(p, 'exit'),
                          icon: const Icon(Icons.logout),
                          label: const Padding(
                            padding: EdgeInsets.symmetric(vertical: 12),
                            child: Text('Saída'),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class PresencePage extends ConsumerStatefulWidget {
  const PresencePage({super.key});
  @override
  ConsumerState<PresencePage> createState() => _PresencePageState();
}

class _PresencePageState extends ConsumerState<PresencePage> {
  late Future<List<dynamic>> future;
  @override
  void initState() {
    super.initState();
    future = load();
  }

  Future<List<dynamic>> load() async {
    final responses = await Future.wait([
      ref.read(apiProvider).dio.get('/presence/person'),
      ref.read(apiProvider).dio.get('/presence/vehicle'),
    ]);
    return [responses[0].data['data'], responses[1].data['data']];
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<List<dynamic>>(
    future: future,
    builder: (c, s) {
      if (!s.hasData) return const Center(child: CircularProgressIndicator());
      final people = s.data![0] as List;
      final vehicles = s.data![1] as List;
      return RefreshIndicator(
        onRefresh: () async {
          setState(() => future = load());
          await future;
        },
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              'Pessoas presentes',
              style: Theme.of(
                c,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            if (people.isEmpty)
              const ListTile(
                leading: Icon(Icons.event_available),
                title: Text('Nenhuma pessoa dentro'),
              ),
            ...people.map(
              (m) => Card(
                child: ListTile(
                  leading: const CircleAvatar(child: Icon(Icons.person)),
                  title: Text(m['person']['name']),
                  subtitle: Text(
                    '${m['person']['category']?['name'] ?? ''} • ${m['location']['name']}',
                  ),
                  trailing: Text(
                    DateFormat(
                      'HH:mm',
                    ).format(DateTime.parse(m['occurred_at']).toLocal()),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 20),
            Text(
              'Veículos presentes',
              style: Theme.of(
                c,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            if (vehicles.isEmpty)
              const ListTile(
                leading: Icon(Icons.garage_outlined),
                title: Text('Nenhum veículo dentro'),
              ),
            ...vehicles.map(
              (m) => Card(
                child: ListTile(
                  leading: const CircleAvatar(
                    child: Icon(Icons.directions_car),
                  ),
                  title: Text(m['vehicle']['plate']),
                  subtitle: Text(
                    '${m['vehicle']['model']} • ${m['person']?['name'] ?? 'Sem motorista'}',
                  ),
                  trailing: Text(
                    DateFormat(
                      'HH:mm',
                    ).format(DateTime.parse(m['occurred_at']).toLocal()),
                  ),
                ),
              ),
            ),
          ],
        ),
      );
    },
  );
}

class HistoryPage extends ConsumerStatefulWidget {
  const HistoryPage({super.key});
  @override
  ConsumerState<HistoryPage> createState() => _HistoryPageState();
}

class _HistoryPageState extends ConsumerState<HistoryPage> {
  late Future<dynamic> f;
  @override
  void initState() {
    super.initState();
    f = ref
        .read(apiProvider)
        .dio
        .get('/movements/person', queryParameters: {'per_page': 100});
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder(
      future: f,
      builder: (c, s) {
        if (!s.hasData) return const Center(child: CircularProgressIndicator());
        final rows = (s.data!.data['data'] as List);
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              'Histórico',
              style: Theme.of(
                c,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),
            ...rows.map(
              (m) => Card(
                child: ListTile(
                  leading: Icon(
                    m['type'] == 'entry' ? Icons.login : Icons.logout,
                  ),
                  title: Text(m['person']?['name'] ?? ''),
                  subtitle: Text(
                    '${m['location']?['name'] ?? ''} • ${m['operator']?['name'] ?? ''}',
                  ),
                  trailing: Text(
                    DateFormat(
                      'dd/MM HH:mm',
                    ).format(DateTime.parse(m['occurred_at']).toLocal()),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}
