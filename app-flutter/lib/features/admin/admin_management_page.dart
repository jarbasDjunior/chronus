import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';

class AdminManagementPage extends ConsumerStatefulWidget {
  const AdminManagementPage({super.key});

  @override
  ConsumerState<AdminManagementPage> createState() =>
      _AdminManagementPageState();
}

class _AdminManagementPageState extends ConsumerState<AdminManagementPage> {
  List<dynamic> employees = [];
  List<dynamic> companies = [];
  List<dynamic> gatekeepers = [];
  List<dynamic> categories = [];
  List<dynamic> departments = [];
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) {
      setState(() {
        loading = true;
        error = null;
      });
    }
    try {
      final api = ref.read(apiProvider);
      final result = await Future.wait([
        api.resource('people'),
        api.resource('security-companies'),
        api.resource('gatekeepers'),
        api.resource('categories'),
        api.resource('departments'),
      ]);
      if (!mounted) return;
      setState(() {
        employees = result[0];
        companies = result[1];
        gatekeepers = result[2];
        categories = result[3];
        departments = result[4];
        loading = false;
      });
    } catch (exception) {
      if (!mounted) return;
      setState(() {
        error = _message(exception);
        loading = false;
      });
    }
  }

  Future<void> employeeForm([Map<String, dynamic>? item]) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => EmployeeFormDialog(
        employee: item,
        categories: categories,
        departments: departments,
      ),
    );
    if (saved == true) await load();
  }

  Future<void> companyForm([Map<String, dynamic>? item]) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => SecurityCompanyFormDialog(company: item),
    );
    if (saved == true) await load();
  }

  Future<void> gatekeeperForm([Map<String, dynamic>? item]) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) =>
          GatekeeperFormDialog(gatekeeper: item, companies: companies),
    );
    if (saved == true) await load();
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    if (error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48),
              const SizedBox(height: 12),
              Text(error!, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: load,
                icon: const Icon(Icons.refresh),
                label: const Text('Tentar novamente'),
              ),
            ],
          ),
        ),
      );
    }

    return DefaultTabController(
      length: 3,
      child: Column(
        children: [
          const TabBar(
            isScrollable: true,
            tabs: [
              Tab(icon: Icon(Icons.groups_outlined), text: 'Funcionários'),
              Tab(icon: Icon(Icons.badge_outlined), text: 'Porteiros'),
              Tab(icon: Icon(Icons.business_outlined), text: 'Terceirizadas'),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: [
                _ManagementList(
                  title: 'Funcionários',
                  addLabel: 'Novo funcionário',
                  emptyLabel: 'Nenhum funcionário cadastrado.',
                  onAdd: employeeForm,
                  onRefresh: load,
                  children: employees.map((raw) {
                    final item = Map<String, dynamic>.from(raw);
                    return Card(
                      child: ListTile(
                        leading: const CircleAvatar(child: Icon(Icons.person)),
                        title: Text(item['name'] ?? ''),
                        subtitle: Text(
                          '${item['registration'] ?? ''} • ${item['department']?['name'] ?? 'Sem departamento'}',
                        ),
                        trailing: IconButton(
                          tooltip: 'Editar funcionário',
                          onPressed: () => employeeForm(item),
                          icon: const Icon(Icons.edit_outlined),
                        ),
                      ),
                    );
                  }).toList(),
                ),
                _ManagementList(
                  title: 'Porteiros terceirizados',
                  addLabel: 'Novo porteiro',
                  emptyLabel: 'Nenhum porteiro cadastrado.',
                  onAdd: gatekeeperForm,
                  onRefresh: load,
                  children: gatekeepers.map((raw) {
                    final item = Map<String, dynamic>.from(raw);
                    return Card(
                      child: ListTile(
                        leading: const CircleAvatar(
                          child: Icon(Icons.security_outlined),
                        ),
                        title: Text(item['name'] ?? ''),
                        subtitle: Text(
                          '${item['registration'] ?? ''} • ${item['company']?['name'] ?? 'Sem empresa'}',
                        ),
                        trailing: IconButton(
                          tooltip: 'Editar porteiro',
                          onPressed: () => gatekeeperForm(item),
                          icon: const Icon(Icons.edit_outlined),
                        ),
                      ),
                    );
                  }).toList(),
                ),
                _ManagementList(
                  title: 'Empresas terceirizadas',
                  addLabel: 'Nova empresa',
                  emptyLabel: 'Nenhuma empresa terceirizada cadastrada.',
                  onAdd: companyForm,
                  onRefresh: load,
                  children: companies.map((raw) {
                    final item = Map<String, dynamic>.from(raw);
                    return Card(
                      child: ListTile(
                        leading: const CircleAvatar(
                          child: Icon(Icons.business_outlined),
                        ),
                        title: Text(item['name'] ?? ''),
                        subtitle: Text(item['cnpj'] ?? 'CNPJ não informado'),
                        trailing: IconButton(
                          tooltip: 'Editar empresa',
                          onPressed: () => companyForm(item),
                          icon: const Icon(Icons.edit_outlined),
                        ),
                      ),
                    );
                  }).toList(),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ManagementList extends StatelessWidget {
  const _ManagementList({
    required this.title,
    required this.addLabel,
    required this.emptyLabel,
    required this.onAdd,
    required this.onRefresh,
    required this.children,
  });

  final String title;
  final String addLabel;
  final String emptyLabel;
  final VoidCallback onAdd;
  final Future<void> Function() onRefresh;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 96),
        children: [
          LayoutBuilder(
            builder: (context, constraints) {
              final heading = Text(
                title,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              );
              final button = FilledButton.icon(
                onPressed: onAdd,
                icon: const Icon(Icons.add),
                label: Text(addLabel),
              );
              if (constraints.maxWidth < 560) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [heading, const SizedBox(height: 12), button],
                );
              }
              return Row(
                children: [
                  Expanded(child: heading),
                  button,
                ],
              );
            },
          ),
          const SizedBox(height: 14),
          if (children.isEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Text(emptyLabel, textAlign: TextAlign.center),
              ),
            ),
          ...children,
        ],
      ),
    );
  }
}

class EmployeeFormDialog extends ConsumerStatefulWidget {
  const EmployeeFormDialog({
    super.key,
    this.employee,
    required this.categories,
    required this.departments,
  });

  final Map<String, dynamic>? employee;
  final List<dynamic> categories;
  final List<dynamic> departments;

  @override
  ConsumerState<EmployeeFormDialog> createState() => _EmployeeFormDialogState();
}

class _EmployeeFormDialogState extends ConsumerState<EmployeeFormDialog> {
  final key = GlobalKey<FormState>();
  late final TextEditingController name;
  late final TextEditingController registration;
  late final TextEditingController jobTitle;
  late final TextEditingController phone;
  late final TextEditingController email;
  int? categoryId;
  int? departmentId;
  bool active = true;
  bool saving = false;

  @override
  void initState() {
    super.initState();
    final item = widget.employee;
    name = TextEditingController(text: item?['name'] ?? '');
    registration = TextEditingController(text: item?['registration'] ?? '');
    jobTitle = TextEditingController(text: item?['job_title'] ?? '');
    phone = TextEditingController(text: item?['phone'] ?? '');
    email = TextEditingController(text: item?['email'] ?? '');
    categoryId =
        item?['category_id'] ??
        (widget.categories.isEmpty ? null : widget.categories.first['id']);
    departmentId = item?['department_id'];
    active = item == null || item['active'] == true || item['active'] == 1;
  }

  @override
  void dispose() {
    name.dispose();
    registration.dispose();
    jobTitle.dispose();
    phone.dispose();
    email.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (!key.currentState!.validate() || saving) return;
    setState(() => saving = true);
    try {
      await ref.read(apiProvider).saveResource('people', {
        'name': name.text.trim(),
        'registration': registration.text.trim(),
        'category_id': categoryId,
        'department_id': departmentId,
        'job_title': _nullable(jobTitle.text),
        'phone': _nullable(phone.text),
        'email': _nullable(email.text),
        'active': active,
      }, id: widget.employee?['id']);
      if (mounted) Navigator.pop(context, true);
    } catch (exception) {
      if (mounted) _showError(context, exception);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _FormDialog(
      title: widget.employee == null
          ? 'Novo funcionário'
          : 'Editar funcionário',
      saving: saving,
      onSave: save,
      child: Form(
        key: key,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _field(name, 'Nome'),
            _gap,
            _field(registration, 'Matrícula'),
            _gap,
            DropdownButtonFormField<int>(
              initialValue: categoryId,
              decoration: const InputDecoration(labelText: 'Categoria'),
              items: widget.categories
                  .map(
                    (item) => DropdownMenuItem<int>(
                      value: item['id'],
                      child: Text(item['name']),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => categoryId = value),
              validator: (value) => value == null ? 'Campo obrigatório' : null,
            ),
            _gap,
            DropdownButtonFormField<int?>(
              initialValue: departmentId,
              decoration: const InputDecoration(labelText: 'Departamento'),
              items: [
                const DropdownMenuItem<int?>(
                  value: null,
                  child: Text('Sem departamento'),
                ),
                ...widget.departments.map(
                  (item) => DropdownMenuItem<int?>(
                    value: item['id'],
                    child: Text(item['name']),
                  ),
                ),
              ],
              onChanged: (value) => setState(() => departmentId = value),
            ),
            _gap,
            _field(jobTitle, 'Cargo', required: false),
            _gap,
            _field(phone, 'Telefone', required: false),
            _gap,
            _field(
              email,
              'E-mail',
              required: false,
              keyboardType: TextInputType.emailAddress,
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Funcionário ativo'),
              value: active,
              onChanged: (value) => setState(() => active = value),
            ),
          ],
        ),
      ),
    );
  }
}

class SecurityCompanyFormDialog extends ConsumerStatefulWidget {
  const SecurityCompanyFormDialog({super.key, this.company});

  final Map<String, dynamic>? company;

  @override
  ConsumerState<SecurityCompanyFormDialog> createState() =>
      _SecurityCompanyFormDialogState();
}

class _SecurityCompanyFormDialogState
    extends ConsumerState<SecurityCompanyFormDialog> {
  final key = GlobalKey<FormState>();
  late final TextEditingController name;
  late final TextEditingController cnpj;
  bool active = true;
  bool saving = false;

  @override
  void initState() {
    super.initState();
    name = TextEditingController(text: widget.company?['name'] ?? '');
    cnpj = TextEditingController(text: widget.company?['cnpj'] ?? '');
    active =
        widget.company == null ||
        widget.company?['active'] == true ||
        widget.company?['active'] == 1;
  }

  @override
  void dispose() {
    name.dispose();
    cnpj.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (!key.currentState!.validate() || saving) return;
    setState(() => saving = true);
    try {
      await ref.read(apiProvider).saveResource('security-companies', {
        'name': name.text.trim(),
        'cnpj': _nullable(cnpj.text),
        'active': active,
      }, id: widget.company?['id']);
      if (mounted) Navigator.pop(context, true);
    } catch (exception) {
      if (mounted) _showError(context, exception);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _FormDialog(
      title: widget.company == null ? 'Nova empresa' : 'Editar empresa',
      saving: saving,
      onSave: save,
      child: Form(
        key: key,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _field(name, 'Razão social'),
            _gap,
            _field(cnpj, 'CNPJ', required: false),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Empresa ativa'),
              value: active,
              onChanged: (value) => setState(() => active = value),
            ),
          ],
        ),
      ),
    );
  }
}

class GatekeeperFormDialog extends ConsumerStatefulWidget {
  const GatekeeperFormDialog({
    super.key,
    this.gatekeeper,
    required this.companies,
  });

  final Map<String, dynamic>? gatekeeper;
  final List<dynamic> companies;

  @override
  ConsumerState<GatekeeperFormDialog> createState() =>
      _GatekeeperFormDialogState();
}

class _GatekeeperFormDialogState extends ConsumerState<GatekeeperFormDialog> {
  final key = GlobalKey<FormState>();
  late final TextEditingController name;
  late final TextEditingController registration;
  late final TextEditingController phone;
  late final TextEditingController email;
  int? companyId;
  bool active = true;
  bool saving = false;

  @override
  void initState() {
    super.initState();
    final item = widget.gatekeeper;
    name = TextEditingController(text: item?['name'] ?? '');
    registration = TextEditingController(text: item?['registration'] ?? '');
    phone = TextEditingController(text: item?['phone'] ?? '');
    email = TextEditingController(text: item?['email'] ?? '');
    companyId =
        item?['security_company_id'] ??
        (widget.companies.isEmpty ? null : widget.companies.first['id']);
    active = item == null || item['active'] == true || item['active'] == 1;
  }

  @override
  void dispose() {
    name.dispose();
    registration.dispose();
    phone.dispose();
    email.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (!key.currentState!.validate() || saving) return;
    setState(() => saving = true);
    try {
      await ref.read(apiProvider).saveResource('gatekeepers', {
        'security_company_id': companyId,
        'user_id': widget.gatekeeper?['user_id'],
        'name': name.text.trim(),
        'registration': registration.text.trim(),
        'phone': _nullable(phone.text),
        'email': _nullable(email.text),
        'active': active,
      }, id: widget.gatekeeper?['id']);
      if (mounted) Navigator.pop(context, true);
    } catch (exception) {
      if (mounted) _showError(context, exception);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _FormDialog(
      title: widget.gatekeeper == null ? 'Novo porteiro' : 'Editar porteiro',
      saving: saving,
      onSave: save,
      child: Form(
        key: key,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _field(name, 'Nome'),
            _gap,
            _field(registration, 'Matrícula'),
            _gap,
            DropdownButtonFormField<int>(
              initialValue: companyId,
              decoration: const InputDecoration(
                labelText: 'Empresa terceirizada',
              ),
              items: widget.companies
                  .map(
                    (item) => DropdownMenuItem<int>(
                      value: item['id'],
                      child: Text(item['name']),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => companyId = value),
              validator: (value) => value == null ? 'Campo obrigatório' : null,
            ),
            _gap,
            _field(phone, 'Telefone', required: false),
            _gap,
            _field(
              email,
              'E-mail',
              required: false,
              keyboardType: TextInputType.emailAddress,
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Porteiro ativo'),
              value: active,
              onChanged: (value) => setState(() => active = value),
            ),
          ],
        ),
      ),
    );
  }
}

class _FormDialog extends StatelessWidget {
  const _FormDialog({
    required this.title,
    required this.saving,
    required this.onSave,
    required this.child,
  });

  final String title;
  final bool saving;
  final VoidCallback onSave;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(title),
      content: SizedBox(width: 500, child: SingleChildScrollView(child: child)),
      actions: [
        TextButton(
          onPressed: saving ? null : () => Navigator.pop(context, false),
          child: const Text('Cancelar'),
        ),
        FilledButton(
          onPressed: saving ? null : onSave,
          child: saving
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Salvar'),
        ),
      ],
    );
  }
}

const _gap = SizedBox(height: 14);

TextFormField _field(
  TextEditingController controller,
  String label, {
  bool required = true,
  TextInputType? keyboardType,
}) {
  return TextFormField(
    controller: controller,
    keyboardType: keyboardType,
    decoration: InputDecoration(labelText: label),
    validator: required
        ? (value) =>
              value == null || value.trim().isEmpty ? 'Campo obrigatório' : null
        : null,
  );
}

String? _nullable(String value) {
  final text = value.trim();
  return text.isEmpty ? null : text;
}

String _message(Object exception) {
  if (exception is DioException) {
    final errors = exception.response?.data?['errors'] as Map?;
    return '${errors?.values.firstOrNull?.first ?? exception.response?.data?['message'] ?? 'Não foi possível concluir a operação.'}';
  }
  return 'Não foi possível concluir a operação.';
}

void _showError(BuildContext context, Object exception) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text(_message(exception)), backgroundColor: Colors.red),
  );
}
