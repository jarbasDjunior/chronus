import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';

class EmployeeDetailsPage extends ConsumerStatefulWidget {
  const EmployeeDetailsPage({super.key, required this.personId});

  final int personId;

  @override
  ConsumerState<EmployeeDetailsPage> createState() =>
      _EmployeeDetailsPageState();
}

class _EmployeeDetailsPageState extends ConsumerState<EmployeeDetailsPage> {
  late Future<Map<String, dynamic>> future;

  bool get canManage {
    final user = ref.read(sessionProvider).valueOrNull;
    if (user?['role']?['slug'] == 'admin') return true;
    final permissions = user?['role']?['permissions'] as List? ?? [];
    return permissions.any(
      (permission) => permission['slug'] == 'registrations.manage',
    );
  }

  @override
  void initState() {
    super.initState();
    future = ref.read(apiProvider).person(widget.personId);
  }

  Future<void> reload() async {
    setState(() {
      future = ref.read(apiProvider).person(widget.personId);
    });
    await future;
  }

  Future<void> editVehicle([Map<String, dynamic>? vehicle]) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) =>
          VehicleFormDialog(personId: widget.personId, vehicle: vehicle),
    );
    if (saved == true) await reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Detalhes do funcionário')),
      floatingActionButton: canManage
          ? FloatingActionButton.extended(
              onPressed: editVehicle,
              icon: const Icon(Icons.add),
              label: const Text('Adicionar veículo'),
            )
          : null,
      body: FutureBuilder<Map<String, dynamic>>(
        future: future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: FilledButton.icon(
                onPressed: reload,
                icon: const Icon(Icons.refresh),
                label: const Text('Tentar novamente'),
              ),
            );
          }
          final person = snapshot.data!;
          final vehicles = person['vehicles'] as List? ?? [];
          return RefreshIndicator(
            onRefresh: reload,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 96),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Row(
                      children: [
                        CircleAvatar(
                          radius: 30,
                          child: Text((person['name'] as String)[0]),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                person['name'],
                                style: Theme.of(context).textTheme.titleLarge,
                              ),
                              Text(
                                '${person['registration']} • ${person['department']?['name'] ?? 'Sem departamento'}',
                              ),
                              if (person['job_title'] != null)
                                Text(person['job_title']),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                Text('Veículos', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 8),
                if (vehicles.isEmpty)
                  const Card(
                    child: ListTile(
                      leading: Icon(Icons.no_crash_outlined),
                      title: Text('Nenhum veículo cadastrado'),
                      subtitle: Text(
                        'Este funcionário ainda não possui veículos vinculados.',
                      ),
                    ),
                  ),
                ...vehicles.map((item) {
                  final vehicle = Map<String, dynamic>.from(item);
                  final active =
                      vehicle['active'] == true || vehicle['active'] == 1;
                  return Card(
                    child: ListTile(
                      leading: const CircleAvatar(
                        child: Icon(Icons.directions_car),
                      ),
                      title: Text(vehicle['plate']),
                      subtitle: Text(
                        '${vehicle['model']} • ${vehicle['color']}',
                      ),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Chip(label: Text(active ? 'Ativo' : 'Inativo')),
                          if (canManage)
                            IconButton(
                              tooltip: 'Editar veículo',
                              onPressed: () => editVehicle(vehicle),
                              icon: const Icon(Icons.edit_outlined),
                            ),
                        ],
                      ),
                    ),
                  );
                }),
              ],
            ),
          );
        },
      ),
    );
  }
}

class VehicleFormDialog extends ConsumerStatefulWidget {
  const VehicleFormDialog({super.key, required this.personId, this.vehicle});

  final int personId;
  final Map<String, dynamic>? vehicle;

  @override
  ConsumerState<VehicleFormDialog> createState() => _VehicleFormDialogState();
}

class _VehicleFormDialogState extends ConsumerState<VehicleFormDialog> {
  final formKey = GlobalKey<FormState>();
  late final TextEditingController plate;
  late final TextEditingController model;
  late final TextEditingController color;
  late bool active;
  bool saving = false;

  @override
  void initState() {
    super.initState();
    plate = TextEditingController(text: widget.vehicle?['plate'] ?? '');
    model = TextEditingController(text: widget.vehicle?['model'] ?? '');
    color = TextEditingController(text: widget.vehicle?['color'] ?? '');
    active =
        widget.vehicle == null ||
        widget.vehicle?['active'] == true ||
        widget.vehicle?['active'] == 1;
  }

  @override
  void dispose() {
    plate.dispose();
    model.dispose();
    color.dispose();
    super.dispose();
  }

  String? required(String? value) =>
      value == null || value.trim().isEmpty ? 'Campo obrigatório' : null;

  Future<void> save() async {
    if (!formKey.currentState!.validate() || saving) return;
    setState(() => saving = true);
    try {
      await ref
          .read(apiProvider)
          .saveVehicle(
            id: widget.vehicle?['id'],
            personId: widget.personId,
            plate: plate.text,
            model: model.text.trim(),
            color: color.text.trim(),
            active: active,
          );
      if (mounted) Navigator.pop(context, true);
    } on DioException catch (error) {
      final errors = error.response?.data?['errors'] as Map?;
      final message =
          errors?.values.firstOrNull?.first ??
          error.response?.data?['message'] ??
          'Não foi possível salvar o veículo.';
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$message'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.vehicle == null ? 'Novo veículo' : 'Editar veículo'),
      content: SizedBox(
        width: 440,
        child: Form(
          key: formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: plate,
                  autofocus: true,
                  textCapitalization: TextCapitalization.characters,
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp('[a-zA-Z0-9-]')),
                    LengthLimitingTextInputFormatter(8),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Placa',
                    hintText: 'ABC1D23',
                  ),
                  validator: required,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: model,
                  decoration: const InputDecoration(labelText: 'Modelo'),
                  validator: required,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: color,
                  decoration: const InputDecoration(labelText: 'Cor'),
                  validator: required,
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Veículo ativo'),
                  value: active,
                  onChanged: (value) => setState(() => active = value),
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: saving ? null : () => Navigator.pop(context, false),
          child: const Text('Cancelar'),
        ),
        FilledButton(
          onPressed: saving ? null : save,
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
