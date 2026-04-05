<?php

require_once dirname(__DIR__) . '/bootstrap.php';

if (!Database::isInstalled()) {
    redirect('install.php');
}

auth()->requireLogin();
auth()->requireManageUsers();

$authUser = auth()->user();
$userList = users()->listUsers();
$surveyCatalog = surveys()->listSurveys();
$roleOptions = users()->roleOptions();

$pageTitle = 'Usuarios';
$pageDescription = 'Cree usuarios administrativos o analistas y asigne qué encuestas puede operar cada uno.';
$currentPage = 'users';
$breadcrumbs = [['title' => 'Usuarios']];

require TEMPLATES_PATH . '/admin_header.php';
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Acceso y asignaciones</h2>
            <p>Controle usuarios internos, rol operativo y encuestas disponibles para cada perfil.</p>
        </div>
        <button class="btn btn-primary" type="button" data-open-modal="userModal" id="createUserButton">Nuevo usuario</button>
    </div>
    <div class="grid-cards">
        <article class="card">
            <div class="metric-value"><?= (int) count($userList) ?></div>
            <div class="metric-label">Usuarios registrados</div>
            <div class="metric-foot">Cuentas habilitadas para operación y análisis</div>
        </article>
        <article class="card">
            <div class="metric-value"><?= count(array_filter($userList, static fn(array $user): bool => $user['status'] === 'active')) ?></div>
            <div class="metric-label">Usuarios activos</div>
            <div class="metric-foot">Con acceso vigente al panel</div>
        </article>
        <article class="card">
            <div class="metric-value"><?= count(array_filter($userList, static fn(array $user): bool => $user['role'] === 'editor')) ?></div>
            <div class="metric-label">Administrativos</div>
            <div class="metric-foot">Gestionan encuestas asignadas</div>
        </article>
        <article class="card">
            <div class="metric-value"><?= count(array_filter($userList, static fn(array $user): bool => $user['role'] === 'analyst')) ?></div>
            <div class="metric-label">Analistas</div>
            <div class="metric-foot">Consumen respuestas y reportes asignados</div>
        </article>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Listado operativo</h2>
            <p>Vista rápida de usuarios, rol y encuestas asignadas.</p>
        </div>
    </div>
    <?php if ($userList === []): ?>
        <div class="empty-state">No hay usuarios registrados todavía.</div>
    <?php else: ?>
        <div class="survey-grid">
            <?php foreach ($userList as $user): ?>
                <article class="survey-card user-card">
                    <div class="actions-inline" style="justify-content:space-between; align-items:flex-start;">
                        <div>
                            <span class="chip chip-muted"><?= e($user['role_label']) ?></span>
                            <h3 style="margin-top:14px;"><?= e($user['full_name']) ?></h3>
                            <p style="margin-bottom:8px;"><?= e($user['email']) ?></p>
                        </div>
                        <span class="<?= e($user['status'] === 'active' ? 'chip chip-success' : 'chip chip-warning') ?>">
                            <?= e($user['status_label']) ?>
                        </span>
                    </div>
                    <div class="meta">
                        <span class="chip chip-muted"><?= (int) count($user['assigned_surveys']) ?> encuestas asignadas</span>
                        <span class="chip chip-muted">Último acceso <?= e(Helpers::formatDateTime($user['last_login_at'])) ?></span>
                    </div>
                    <div class="assignment-chip-list">
                        <?php if ($user['role'] === 'super_admin'): ?>
                            <span class="chip chip-success">Acceso total al sistema</span>
                        <?php elseif ($user['assigned_surveys'] === []): ?>
                            <span class="chip chip-warning">Sin encuestas asignadas</span>
                        <?php else: ?>
                            <?php foreach (array_slice($user['assigned_surveys'], 0, 4) as $assignment): ?>
                                <span class="chip chip-muted"><?= e($assignment['survey_name']) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($user['assigned_surveys']) > 4): ?>
                                <span class="chip chip-muted">+<?= count($user['assigned_surveys']) - 4 ?> más</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="actions-inline" style="margin-top:14px;">
                        <button class="btn btn-secondary js-edit-user" type="button" data-id="<?= (int) $user['id'] ?>" data-open-modal="userModal">Editar</button>
                        <?php if ((int) $user['id'] !== (int) ($authUser['id'] ?? 0)): ?>
                            <button class="btn btn-danger js-delete-user" type="button" data-id="<?= (int) $user['id'] ?>">Eliminar</button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Resumen tabular</h2>
            <p>Control administrativo para seguimiento y auditoría.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Encuestas asignadas</th>
                <th>Último ingreso</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($userList as $user): ?>
                <tr>
                    <td>
                        <strong><?= e($user['full_name']) ?></strong><br>
                        <small><?= e($user['email']) ?></small>
                    </td>
                    <td><?= e($user['role_label']) ?></td>
                    <td><?= e($user['status_label']) ?></td>
                    <td><?= $user['role'] === 'super_admin' ? 'Todas las encuestas' : (int) count($user['assigned_surveys']) ?></td>
                    <td><?= e(Helpers::formatDateTime($user['last_login_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal" id="userModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <h3 id="userModalTitle">Nuevo usuario</h3>
                <p>Defina el perfil y asigne las encuestas que podrá administrar o analizar.</p>
            </div>
            <button class="btn btn-secondary" type="button" data-close-modal>Cerrar</button>
        </div>
        <form id="userForm">
            <div class="modal-body stack">
                <input type="hidden" name="id" id="user_id">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <div class="form-grid">
                    <div class="field">
                        <label>Nombre completo</label>
                        <input type="text" name="full_name" id="user_full_name" required>
                    </div>
                    <div class="field">
                        <label>Correo</label>
                        <input type="email" name="email" id="user_email" required>
                    </div>
                </div>
                <div class="form-grid-3">
                    <div class="field">
                        <label>Rol</label>
                        <select name="role" id="user_role" required>
                            <?php foreach ($roleOptions as $role): ?>
                                <option value="<?= e($role['value']) ?>"><?= e($role['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Estado</label>
                        <select name="status" id="user_status" required>
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </div>
                    <div class="field">
                        <label id="userPasswordLabel">Contraseña inicial</label>
                        <input type="password" name="password" id="user_password" minlength="8">
                    </div>
                </div>
                <div class="panel panel-muted role-help" id="roleHelpCard">
                    <strong id="roleHelpTitle">Administrativo</strong>
                    <p id="roleHelpDescription">Gestiona encuestas asignadas.</p>
                </div>
                <div class="field" id="assignmentField">
                    <label>Encuestas asignadas</label>
                    <?php if ($surveyCatalog === []): ?>
                        <div class="empty-state">Primero cree al menos una encuesta para poder asignarla a usuarios.</div>
                    <?php else: ?>
                        <div class="assignment-grid" id="assignmentGrid">
                            <?php foreach ($surveyCatalog as $survey): ?>
                                <label class="assignment-option">
                                    <input type="checkbox" name="assigned_surveys[]" value="<?= (int) $survey['id'] ?>">
                                    <span>
                                        <strong><?= e($survey['name']) ?></strong>
                                        <small><?= e($survey['slug']) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <small>Los usuarios no super administradores sólo verán las encuestas seleccionadas aquí.</small>
                </div>
            </div>
            <div class="modal-footer">
                <span class="chip chip-muted">La contraseña puede dejarse vacía al editar si no desea cambiarla.</span>
                <button class="btn btn-primary" type="submit">Guardar usuario</button>
            </div>
        </form>
    </div>
</div>

<script>
const userData = <?= json_encode($userList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const roleOptions = <?= json_encode($roleOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const userForm = document.getElementById('userForm');

function resetAssignments() {
    document.querySelectorAll('#assignmentGrid input[type="checkbox"]').forEach((input) => {
        input.checked = false;
    });
}

function updateRoleState() {
    const role = document.getElementById('user_role').value;
    const roleMeta = roleOptions.find((item) => item.value === role);
    const assignmentField = document.getElementById('assignmentField');
    const inputs = document.querySelectorAll('#assignmentGrid input[type="checkbox"]');
    const isSuperAdmin = role === 'super_admin';

    document.getElementById('roleHelpTitle').textContent = roleMeta?.label || 'Perfil';
    document.getElementById('roleHelpDescription').textContent = roleMeta?.description || '';
    document.getElementById('userPasswordLabel').textContent = document.getElementById('user_id').value ? 'Nueva contraseña' : 'Contraseña inicial';

    if (assignmentField) {
        assignmentField.style.display = isSuperAdmin ? 'none' : 'grid';
    }

    inputs.forEach((input) => {
        input.disabled = isSuperAdmin;
        if (isSuperAdmin) {
            input.checked = false;
        }
    });
}

function resetUserForm() {
    userForm.reset();
    document.getElementById('user_id').value = '';
    document.getElementById('user_status').value = 'active';
    document.getElementById('user_role').value = 'editor';
    document.getElementById('userModalTitle').textContent = 'Nuevo usuario';
    resetAssignments();
    updateRoleState();
}

document.getElementById('createUserButton').addEventListener('click', resetUserForm);
document.getElementById('user_role').addEventListener('change', updateRoleState);

document.querySelectorAll('.js-edit-user').forEach((button) => {
    button.addEventListener('click', () => {
        const user = userData.find((item) => Number(item.id) === Number(button.dataset.id));
        if (!user) {
            return;
        }

        resetUserForm();
        document.getElementById('userModalTitle').textContent = 'Editar usuario';
        document.getElementById('user_id').value = user.id;
        document.getElementById('user_full_name').value = user.full_name ?? '';
        document.getElementById('user_email').value = user.email ?? '';
        document.getElementById('user_role').value = user.role ?? 'editor';
        document.getElementById('user_status').value = user.status ?? 'active';
        document.getElementById('user_password').value = '';

        const assignedIds = new Set((user.assigned_survey_ids || []).map((item) => Number(item)));
        document.querySelectorAll('#assignmentGrid input[type="checkbox"]').forEach((input) => {
            input.checked = assignedIds.has(Number(input.value));
        });

        updateRoleState();
    });
});

userForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(userForm);
    const isEditing = Boolean(formData.get('id'));
    const response = await fetch('<?= url('api/admin/app.php?action=save_user') ?>', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': formData.get('<?= e(CSRF_TOKEN_NAME) ?>')},
        body: formData,
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
        window.ShalomApp.notify('error', 'No se pudo guardar el usuario', result.message || 'Revise la información e intente nuevamente.');
        return;
    }

    window.ShalomApp.queueToast({
        type: 'success',
        title: isEditing ? 'Usuario actualizado' : 'Usuario creado',
        message: 'Los permisos y asignaciones quedaron guardados.',
    });
    window.location.reload();
});

document.querySelectorAll('.js-delete-user').forEach((button) => {
    button.addEventListener('click', async () => {
        const confirmed = await window.ShalomApp.confirm({
            title: 'Eliminar usuario',
            message: 'Se retirará el acceso del usuario y sus asignaciones de encuestas.',
            confirmText: 'Eliminar usuario',
            confirmClass: 'btn btn-danger',
        });

        if (!confirmed) {
            return;
        }

        const formData = new FormData();
        formData.append('id', button.dataset.id);
        formData.append('<?= e(CSRF_TOKEN_NAME) ?>', '<?= e(csrf_token()) ?>');

        const response = await fetch('<?= url('api/admin/app.php?action=delete_user') ?>', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '<?= e(csrf_token()) ?>'},
            body: formData,
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            window.ShalomApp.notify('error', 'No se pudo eliminar el usuario', result.message || 'Intente nuevamente.');
            return;
        }

        window.ShalomApp.queueToast({
            type: 'success',
            title: 'Usuario eliminado',
            message: 'La cuenta fue retirada del panel administrativo.',
        });
        window.location.reload();
    });
});

updateRoleState();
</script>
<?php require TEMPLATES_PATH . '/admin_footer.php'; ?>
