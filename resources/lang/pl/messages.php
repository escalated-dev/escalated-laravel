<?php

return [

    'ticket' => [
        'reply_sent' => 'Odpowiedź wysłana.',
        'note_added' => 'Notatka dodana.',
        'assigned' => 'Zgłoszenie przypisane.',
        'status_updated' => 'Status zaktualizowany.',
        'priority_updated' => 'Priorytet zaktualizowany.',
        'tags_updated' => 'Tagi zaktualizowane.',
        'department_updated' => 'Dział zaktualizowany.',
        'macro_applied' => 'Makro ":name" zastosowane.',
        'following' => 'Obserwujesz zgłoszenie.',
        'unfollowed' => 'Przestałeś obserwować zgłoszenie.',
        'only_internal_notes_pinned' => 'Tylko notatki wewnętrzne mogą być przypięte.',
        'updated' => 'Zgłoszenie zaktualizowane.',
        'created' => 'Zgłoszenie utworzone pomyślnie.',
        'closed' => 'Zgłoszenie zamknięte.',
        'reopened' => 'Zgłoszenie ponownie otwarte.',
        'customers_cannot_close' => 'Klienci nie mogą zamykać zgłoszeń.',
    ],

    'guest' => [
        'created' => 'Zgłoszenie utworzone. Zapisz ten link, aby sprawdzić status zgłoszenia.',
        'ticket_closed' => 'To zgłoszenie jest zamknięte.',
    ],

    'bulk' => [
        'updated' => 'Zaktualizowano :count zgłoszenie/zgłoszeń.',
    ],

    'rating' => [
        'only_resolved_closed' => 'Możesz oceniać tylko rozwiązane lub zamknięte zgłoszenia.',
        'already_rated' => 'To zgłoszenie zostało już ocenione.',
        'thanks' => 'Dziękujemy za opinię!',
    ],

    'canned_response' => [
        'created' => 'Szablon odpowiedzi utworzony.',
        'updated' => 'Szablon odpowiedzi zaktualizowany.',
        'deleted' => 'Szablon odpowiedzi usunięty.',
    ],

    'department' => [
        'created' => 'Dział utworzony.',
        'updated' => 'Dział zaktualizowany.',
        'deleted' => 'Dział usunięty.',
    ],

    'tag' => [
        'created' => 'Tag utworzony.',
        'updated' => 'Tag zaktualizowany.',
        'deleted' => 'Tag usunięty.',
    ],

    'macro' => [
        'created' => 'Makro utworzone.',
        'updated' => 'Makro zaktualizowane.',
        'deleted' => 'Makro usunięte.',
    ],

    'escalation_rule' => [
        'created' => 'Reguła utworzona.',
        'updated' => 'Reguła zaktualizowana.',
        'deleted' => 'Reguła usunięta.',
    ],

    'sla_policy' => [
        'created' => 'Polityka SLA utworzona.',
        'updated' => 'Polityka SLA zaktualizowana.',
        'deleted' => 'Polityka SLA usunięta.',
    ],

    'settings' => [
        'updated' => 'Ustawienia zaktualizowane.',
    ],

    'plugin' => [
        'uploaded' => 'Wtyczka przesłana pomyślnie. Możesz ją teraz aktywować.',
        'upload_failed' => 'Nie udało się przesłać wtyczki: :error',
        'activated' => 'Wtyczka aktywowana pomyślnie.',
        'activate_failed' => 'Nie udało się aktywować wtyczki: :error',
        'deactivated' => 'Wtyczka dezaktywowana pomyślnie.',
        'deactivate_failed' => 'Nie udało się dezaktywować wtyczki: :error',
        'composer_delete' => 'Wtyczki Composer nie mogą być usunięte. Usuń pakiet przez Composer.',
        'deleted' => 'Wtyczka usunięta pomyślnie.',
        'delete_failed' => 'Nie udało się usunąć wtyczki: :error',
    ],

    'middleware' => [
        'not_admin' => 'Nie masz uprawnień administratora wsparcia.',
        'not_agent' => 'Nie masz uprawnień agenta wsparcia.',
    ],

    'inbound_email' => [
        'disabled' => 'Poczta przychodząca jest wyłączona.',
        'unknown_adapter' => 'Nieznany adapter.',
        'invalid_signature' => 'Nieprawidłowy podpis.',
        'processing_failed' => 'Przetwarzanie nie powiodło się.',
    ],

];
