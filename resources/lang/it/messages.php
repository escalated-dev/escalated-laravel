<?php

return [

    'ticket' => [
        'reply_sent' => 'Risposta inviata.',
        'note_added' => 'Nota aggiunta.',
        'assigned' => 'Ticket assegnato.',
        'status_updated' => 'Stato aggiornato.',
        'priority_updated' => 'Priorità aggiornata.',
        'tags_updated' => 'Tag aggiornati.',
        'department_updated' => 'Reparto aggiornato.',
        'macro_applied' => 'Macro ":name" applicata.',
        'following' => 'Stai seguendo il ticket.',
        'unfollowed' => 'Non segui più il ticket.',
        'only_internal_notes_pinned' => 'Solo le note interne possono essere fissate.',
        'updated' => 'Ticket aggiornato.',
        'created' => 'Ticket creato con successo.',
        'closed' => 'Ticket chiuso.',
        'reopened' => 'Ticket riaperto.',
        'customers_cannot_close' => 'I clienti non possono chiudere i ticket.',
    ],

    'guest' => [
        'created' => 'Ticket creato. Salva questo link per verificare lo stato del tuo ticket.',
        'ticket_closed' => 'Questo ticket è chiuso.',
    ],

    'bulk' => [
        'updated' => ':count ticket aggiornato/i.',
    ],

    'rating' => [
        'only_resolved_closed' => 'Puoi valutare solo i ticket risolti o chiusi.',
        'already_rated' => 'Questo ticket è già stato valutato.',
        'thanks' => 'Grazie per il tuo feedback!',
    ],

    'canned_response' => [
        'created' => 'Risposta predefinita creata.',
        'updated' => 'Risposta predefinita aggiornata.',
        'deleted' => 'Risposta predefinita eliminata.',
    ],

    'department' => [
        'created' => 'Reparto creato.',
        'updated' => 'Reparto aggiornato.',
        'deleted' => 'Reparto eliminato.',
    ],

    'tag' => [
        'created' => 'Tag creato.',
        'updated' => 'Tag aggiornato.',
        'deleted' => 'Tag eliminato.',
    ],

    'macro' => [
        'created' => 'Macro creata.',
        'updated' => 'Macro aggiornata.',
        'deleted' => 'Macro eliminata.',
    ],

    'escalation_rule' => [
        'created' => 'Regola creata.',
        'updated' => 'Regola aggiornata.',
        'deleted' => 'Regola eliminata.',
    ],

    'sla_policy' => [
        'created' => 'Policy SLA creata.',
        'updated' => 'Policy SLA aggiornata.',
        'deleted' => 'Policy SLA eliminata.',
    ],

    'settings' => [
        'updated' => 'Impostazioni aggiornate.',
    ],

    'plugin' => [
        'uploaded' => 'Plugin caricato con successo. Ora puoi attivarlo.',
        'upload_failed' => 'Caricamento del plugin fallito: :error',
        'activated' => 'Plugin attivato con successo.',
        'activate_failed' => 'Attivazione del plugin fallita: :error',
        'deactivated' => 'Plugin disattivato con successo.',
        'deactivate_failed' => 'Disattivazione del plugin fallita: :error',
        'composer_delete' => 'I plugin Composer non possono essere eliminati. Rimuovi il pacchetto tramite Composer.',
        'deleted' => 'Plugin eliminato con successo.',
        'delete_failed' => 'Eliminazione del plugin fallita: :error',
    ],

    'middleware' => [
        'not_admin' => 'Non sei autorizzato come amministratore del supporto.',
        'not_agent' => 'Non sei autorizzato come agente del supporto.',
    ],

    'inbound_email' => [
        'disabled' => "L'e-mail in entrata è disabilitata.",
        'unknown_adapter' => 'Adattatore sconosciuto.',
        'invalid_signature' => 'Firma non valida.',
        'processing_failed' => 'Elaborazione fallita.',
    ],

];
