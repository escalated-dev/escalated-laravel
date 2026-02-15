<?php

return [

    'ticket' => [
        'reply_sent' => 'Respuesta enviada.',
        'note_added' => 'Nota añadida.',
        'assigned' => 'Ticket asignado.',
        'status_updated' => 'Estado actualizado.',
        'priority_updated' => 'Prioridad actualizada.',
        'tags_updated' => 'Etiquetas actualizadas.',
        'department_updated' => 'Departamento actualizado.',
        'macro_applied' => 'Macro ":name" aplicada.',
        'following' => 'Siguiendo ticket.',
        'unfollowed' => 'Dejó de seguir el ticket.',
        'only_internal_notes_pinned' => 'Solo se pueden fijar notas internas.',
        'updated' => 'Ticket actualizado.',
        'created' => 'Ticket creado exitosamente.',
        'closed' => 'Ticket cerrado.',
        'reopened' => 'Ticket reabierto.',
        'customers_cannot_close' => 'Los clientes no pueden cerrar tickets.',
    ],

    'guest' => [
        'created' => 'Ticket creado. Guarde este enlace para consultar el estado de su ticket.',
        'ticket_closed' => 'Este ticket está cerrado.',
    ],

    'bulk' => [
        'updated' => ':count ticket(s) actualizado(s).',
    ],

    'rating' => [
        'only_resolved_closed' => 'Solo puede calificar tickets resueltos o cerrados.',
        'already_rated' => 'Este ticket ya ha sido calificado.',
        'thanks' => '¡Gracias por sus comentarios!',
    ],

    'canned_response' => [
        'created' => 'Respuesta predefinida creada.',
        'updated' => 'Respuesta predefinida actualizada.',
        'deleted' => 'Respuesta predefinida eliminada.',
    ],

    'department' => [
        'created' => 'Departamento creado.',
        'updated' => 'Departamento actualizado.',
        'deleted' => 'Departamento eliminado.',
    ],

    'tag' => [
        'created' => 'Etiqueta creada.',
        'updated' => 'Etiqueta actualizada.',
        'deleted' => 'Etiqueta eliminada.',
    ],

    'macro' => [
        'created' => 'Macro creada.',
        'updated' => 'Macro actualizada.',
        'deleted' => 'Macro eliminada.',
    ],

    'escalation_rule' => [
        'created' => 'Regla creada.',
        'updated' => 'Regla actualizada.',
        'deleted' => 'Regla eliminada.',
    ],

    'sla_policy' => [
        'created' => 'Política SLA creada.',
        'updated' => 'Política SLA actualizada.',
        'deleted' => 'Política SLA eliminada.',
    ],

    'settings' => [
        'updated' => 'Configuración actualizada.',
    ],

    'plugin' => [
        'uploaded' => 'Plugin cargado exitosamente. Ahora puede activarlo.',
        'upload_failed' => 'Error al cargar el plugin: :error',
        'activated' => 'Plugin activado exitosamente.',
        'activate_failed' => 'Error al activar el plugin: :error',
        'deactivated' => 'Plugin desactivado exitosamente.',
        'deactivate_failed' => 'Error al desactivar el plugin: :error',
        'composer_delete' => 'Los plugins de Composer no se pueden eliminar. Elimine el paquete a través de Composer.',
        'deleted' => 'Plugin eliminado exitosamente.',
        'delete_failed' => 'Error al eliminar el plugin: :error',
    ],

    'middleware' => [
        'not_admin' => 'No está autorizado como administrador de soporte.',
        'not_agent' => 'No está autorizado como agente de soporte.',
    ],

    'inbound_email' => [
        'disabled' => 'El correo entrante está deshabilitado.',
        'unknown_adapter' => 'Adaptador desconocido.',
        'invalid_signature' => 'Firma no válida.',
        'processing_failed' => 'Error en el procesamiento.',
    ],

];
