<?php

return [

    'ticket' => [
        'reply_sent' => 'Antwoord verzonden.',
        'note_added' => 'Notitie toegevoegd.',
        'assigned' => 'Ticket toegewezen.',
        'status_updated' => 'Status bijgewerkt.',
        'priority_updated' => 'Prioriteit bijgewerkt.',
        'tags_updated' => 'Tags bijgewerkt.',
        'department_updated' => 'Afdeling bijgewerkt.',
        'macro_applied' => 'Macro ":name" toegepast.',
        'following' => 'U volgt dit ticket.',
        'unfollowed' => 'U volgt dit ticket niet meer.',
        'only_internal_notes_pinned' => 'Alleen interne notities kunnen worden vastgepind.',
        'updated' => 'Ticket bijgewerkt.',
        'created' => 'Ticket succesvol aangemaakt.',
        'closed' => 'Ticket gesloten.',
        'reopened' => 'Ticket heropend.',
        'customers_cannot_close' => 'Klanten kunnen tickets niet sluiten.',
    ],

    'guest' => [
        'created' => 'Ticket aangemaakt. Bewaar deze link om de status van uw ticket te controleren.',
        'ticket_closed' => 'Dit ticket is gesloten.',
    ],

    'bulk' => [
        'updated' => ':count ticket(s) bijgewerkt.',
    ],

    'rating' => [
        'only_resolved_closed' => 'U kunt alleen opgeloste of gesloten tickets beoordelen.',
        'already_rated' => 'Dit ticket is al beoordeeld.',
        'thanks' => 'Bedankt voor uw feedback!',
    ],

    'canned_response' => [
        'created' => 'Standaardantwoord aangemaakt.',
        'updated' => 'Standaardantwoord bijgewerkt.',
        'deleted' => 'Standaardantwoord verwijderd.',
    ],

    'department' => [
        'created' => 'Afdeling aangemaakt.',
        'updated' => 'Afdeling bijgewerkt.',
        'deleted' => 'Afdeling verwijderd.',
    ],

    'tag' => [
        'created' => 'Tag aangemaakt.',
        'updated' => 'Tag bijgewerkt.',
        'deleted' => 'Tag verwijderd.',
    ],

    'macro' => [
        'created' => 'Macro aangemaakt.',
        'updated' => 'Macro bijgewerkt.',
        'deleted' => 'Macro verwijderd.',
    ],

    'escalation_rule' => [
        'created' => 'Regel aangemaakt.',
        'updated' => 'Regel bijgewerkt.',
        'deleted' => 'Regel verwijderd.',
    ],

    'sla_policy' => [
        'created' => 'SLA-beleid aangemaakt.',
        'updated' => 'SLA-beleid bijgewerkt.',
        'deleted' => 'SLA-beleid verwijderd.',
    ],

    'settings' => [
        'updated' => 'Instellingen bijgewerkt.',
    ],

    'plugin' => [
        'uploaded' => 'Plugin succesvol geüpload. U kunt deze nu activeren.',
        'upload_failed' => 'Plugin uploaden mislukt: :error',
        'activated' => 'Plugin succesvol geactiveerd.',
        'activate_failed' => 'Plugin activeren mislukt: :error',
        'deactivated' => 'Plugin succesvol gedeactiveerd.',
        'deactivate_failed' => 'Plugin deactiveren mislukt: :error',
        'composer_delete' => 'Composer-plugins kunnen niet worden verwijderd. Verwijder het pakket via Composer.',
        'deleted' => 'Plugin succesvol verwijderd.',
        'delete_failed' => 'Plugin verwijderen mislukt: :error',
    ],

    'middleware' => [
        'not_admin' => 'U bent niet geautoriseerd als supportbeheerder.',
        'not_agent' => 'U bent niet geautoriseerd als supportmedewerker.',
    ],

    'inbound_email' => [
        'disabled' => 'Inkomende e-mail is uitgeschakeld.',
        'unknown_adapter' => 'Onbekende adapter.',
        'invalid_signature' => 'Ongeldige handtekening.',
        'processing_failed' => 'Verwerking mislukt.',
    ],

];
