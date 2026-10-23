<?php

return [
    'title' => 'Payment',
    'meta_description' => 'Complete your QRIS payment for a 3D Expert course or event.',
    'breadcrumb' => 'Payment',

    'kind_event' => 'Event Registration',
    'kind_course' => 'Course Registration',

    'summary_heading' => 'Order Summary',
    'order_id' => 'Order ID',
    'amount' => 'Total Payment',
    'status' => 'Status',
    'method' => 'Method',
    'method_qris' => 'QRIS',
    'created_at' => 'Created',
    'expires_at' => 'Payment Deadline',
    'reference' => 'Ticket Code',
    'customer' => 'Customer',
    'event_when' => 'Event Time',
    'event_where' => 'Location',

    'qr_heading' => 'Scan QRIS',
    'qr_instruction' => 'Open your bank or e-wallet app, choose QRIS, then scan the code below.',
    'qr_alt' => 'Payment QRIS code',
    'qr_waiting' => 'Preparing your QRIS code...',
    'qr_unavailable_title' => 'QRIS payment is not available yet',
    'qr_unavailable_body' => 'We could not create the QR code. Reload this page in a moment or contact the admin to finish your payment.',
    'contact_admin' => 'Contact Admin',
    'retry_charge' => 'Reload Page',

    'countdown_label' => 'Complete payment within',
    'countdown_note' => 'Your seat or access is held for :minutes minutes. When the time is up, the order is cancelled automatically.',
    'countdown_expired' => 'The payment window has ended.',

    'status_pending' => 'Awaiting Payment',
    'status_settlement' => 'Payment Successful',
    'status_expired' => 'Expired',
    'status_cancelled' => 'Cancelled',
    'status_failed' => 'Failed',
    'status_refunded' => 'Refunded',

    'pending_title' => 'Awaiting payment',
    'pending_body' => 'Your order has been created and we have not received the payment yet.',

    'settled_title' => 'Payment successful',
    'settled_body' => 'Thank you. We have received your payment and your access/registration is now active.',
    'settled_note' => 'Keep this Order ID as your payment reference.',
    'success_action' => 'Continue',

    'expired_title' => 'Payment expired',
    'expired_body' => 'The payment deadline has passed. For event registrations, your held seat has been released automatically.',
    'cancelled_title' => 'Payment cancelled',
    'cancelled_body' => 'This payment has been cancelled. You can create a new order if you still want to continue.',
    'failed_title' => 'Payment failed',
    'failed_body' => 'The payment could not be processed. You can try again with a new order.',
    'refunded_title' => 'Payment refunded',
    'refunded_body' => 'This payment has been refunded. Contact the admin if you need help.',

    'retry_action' => 'Go Back and Try Again',
    'back_action' => 'Back',
    'secure_note' => 'Payment is processed by Midtrans. 3D Expert does not store your card data or PIN.',
];
