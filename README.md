# Shalom Encuestas

Sistema web para administrar múltiples encuestas con:

- Panel administrativo con login.
- Gestión de usuarios con roles y asignación de encuestas.
- Creación de encuestas con fecha de inicio y cierre.
- Gestión por secciones y preguntas parametrizables.
- Carga masiva de preguntas por `JSON` o `CSV`.
- Formulario público responsive.
- Reportes y estadísticas con tendencias, cobertura y hallazgos.

## Estructura principal

- `install.php`: instalador inicial de base de datos y semilla.
- `dashboard.php`: resumen ejecutivo.
- `encuestas/`: administración de encuestas y editor estructural.
- `respuestas/`: consulta de formularios enviados.
- `reportes/`: analítica y visualizaciones.
- `usuarios/`: administración de usuarios internos y permisos.
- `public/`: formulario público.
- `api/admin/app.php`: endpoint operativo del panel.
- `api/public/app.php`: envío público de respuestas.
- `database/schema.sql`: esquema de tablas.
- `database/create_database.sql`: creación explícita de base de datos.
- `database/patch_user_assignments.sql`: migración para asignación de encuestas a usuarios existentes.

## Credenciales iniciales

- Usuario: `admin@shalom.local`
- Clave: `Shalom2026!`

## Instalación

1. Abra `install.php` en el navegador.
2. Si necesita otro host, usuario o clave de base de datos, edítelo en el formulario de instalación.
3. Confirme la instalación.
4. Ingrese con las credenciales iniciales.

Por defecto la configuración usa:

- Host: `127.0.0.1`
- Puerto: `3306`
- Base: `encuestas2026`
- Usuario: `root`
- Clave: `12345678`

Puede sobreescribirlos con variables de entorno `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
Si cambia el host, el usuario o la clave desde `install.php`, el instalador los guarda en `config/local.php`. Las variables de entorno siguen teniendo prioridad.

## Roles disponibles

- `Super administrador`: acceso total a usuarios, encuestas, respuestas y reportes.
- `Administrativo`: administra únicamente las encuestas asignadas.
- `Analista`: consulta respuestas y reportes de las encuestas asignadas.

## Migración para instalaciones existentes

Si el sistema ya está instalado y desea habilitar asignación de encuestas por usuario, aplique:

```sql
SOURCE database/patch_user_assignments.sql;
```

## Carga masiva

### JSON

```json
{
  "sections": [
    {
      "title": "Sección 1",
      "description": "Descripción",
      "sort_order": 1,
      "questions": [
        {
          "code": "Q1",
          "prompt": "Pregunta",
          "question_type": "single_choice",
          "is_required": true,
          "options": [
            {"code": "SI", "label": "Sí"},
            {"code": "NO", "label": "No"}
          ]
        }
      ]
    }
  ]
}
```

### CSV

Encabezados soportados:

```text
section_title,section_description,code,prompt,question_type,is_required,options,visibility_question_code,visibility_operator,visibility_value
```

En `options` use `|` para separar valores:

```text
SI|Sí|NO|No
```
