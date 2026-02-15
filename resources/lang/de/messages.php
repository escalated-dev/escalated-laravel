<?php

return [

    'ticket' => [
        'reply_sent' => 'Antwort gesendet.',
        'note_added' => 'Notiz hinzugefügt.',
        'assigned' => 'Ticket zugewiesen.',
        'status_updated' => 'Status aktualisiert.',
        'priority_updated' => 'Priorität aktualisiert.',
        'tags_updated' => 'Tags aktualisiert.',
        'department_updated' => 'Abteilung aktualisiert.',
        'macro_applied' => 'Makro „:name" angewendet.',
        'following' => 'Ticket wird verfolgt.',
        'unfollowed' => 'Ticket wird nicht mehr verfolgt.',
        'only_internal_notes_pinned' => 'Nur interne Notizen können angeheftet werden.',
        'updated' => 'Ticket aktualisiert.',
        'created' => 'Ticket erfolgreich erstellt.',
        'closed' => 'Ticket geschlossen.',
        'reopened' => 'Ticket wiedereröffnet.',
        'customers_cannot_close' => 'Kunden können Tickets nicht schließen.',
    ],

    'guest' => [
        'created' => 'Ticket erstellt. Speichern Sie diesen Link, um den Status Ihres Tickets zu überprüfen.',
        'ticket_closed' => 'Dieses Ticket ist geschlossen.',
    ],

    'bulk' => [
        'updated' => ':count Ticket(s) aktualisiert.',
    ],

    'rating' => [
        'only_resolved_closed' => 'Sie können nur gelöste oder geschlossene Tickets bewerten.',
        'already_rated' => 'Dieses Ticket wurde bereits bewertet.',
        'thanks' => 'Vielen Dank für Ihr Feedback!',
    ],

    'canned_response' => [
        'created' => 'Vordefinierte Antwort erstellt.',
        'updated' => 'Vordefinierte Antwort aktualisiert.',
        'deleted' => 'Vordefinierte Antwort gelöscht.',
    ],

    'department' => [
        'created' => 'Abteilung erstellt.',
        'updated' => 'Abteilung aktualisiert.',
        'deleted' => 'Abteilung gelöscht.',
    ],

    'tag' => [
        'created' => 'Tag erstellt.',
        'updated' => 'Tag aktualisiert.',
        'deleted' => 'Tag gelöscht.',
    ],

    'macro' => [
        'created' => 'Makro erstellt.',
        'updated' => 'Makro aktualisiert.',
        'deleted' => 'Makro gelöscht.',
    ],

    'escalation_rule' => [
        'created' => 'Regel erstellt.',
        'updated' => 'Regel aktualisiert.',
        'deleted' => 'Regel gelöscht.',
    ],

    'sla_policy' => [
        'created' => 'SLA-Richtlinie erstellt.',
        'updated' => 'SLA-Richtlinie aktualisiert.',
        'deleted' => 'SLA-Richtlinie gelöscht.',
    ],

    'settings' => [
        'updated' => 'Einstellungen aktualisiert.',
    ],

    'plugin' => [
        'uploaded' => 'Plugin erfolgreich hochgeladen. Sie können es jetzt aktivieren.',
        'upload_failed' => 'Plugin-Upload fehlgeschlagen: :error',
        'activated' => 'Plugin erfolgreich aktiviert.',
        'activate_failed' => 'Plugin-Aktivierung fehlgeschlagen: :error',
        'deactivated' => 'Plugin erfolgreich deaktiviert.',
        'deactivate_failed' => 'Plugin-Deaktivierung fehlgeschlagen: :error',
        'composer_delete' => 'Composer-Plugins können nicht gelöscht werden. Entfernen Sie das Paket über Composer.',
        'deleted' => 'Plugin erfolgreich gelöscht.',
        'delete_failed' => 'Plugin-Löschung fehlgeschlagen: :error',
    ],

    'middleware' => [
        'not_admin' => 'Sie sind nicht als Support-Administrator autorisiert.',
        'not_agent' => 'Sie sind nicht als Support-Mitarbeiter autorisiert.',
    ],

    'inbound_email' => [
        'disabled' => 'Eingehende E-Mails sind deaktiviert.',
        'unknown_adapter' => 'Unbekannter Adapter.',
        'invalid_signature' => 'Ungültige Signatur.',
        'processing_failed' => 'Verarbeitung fehlgeschlagen.',
    ],

];
