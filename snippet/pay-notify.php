<?php
/**
 * Standalone webhook для NotificationURL Т‑Банк.
 * Подключите через MODX connector или укажите URL ресурса со сниппетом PaymentHandler.
 *
 * Рекомендуемый вариант: ресурс /pay-notify/ с содержимым [[!PaymentHandler? &action=`pay_notify`]]
 * Этот файл — fallback, если сниппет вызывается через внешний bootstrap.
 */
header('Content-Type: text/plain; charset=utf-8');
echo 'OK';
