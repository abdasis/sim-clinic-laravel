<?php

return [
    'patients' => [
        'title' => 'Every new patient starts with one card',
        'description' => 'Capture the basics once; visit history and medical records stay attached from then on.',
        'action' => 'Add patient',
    ],
    'services' => [
        'title' => 'A tidy catalogue means the cashier never guesses',
        'description' => 'Set name, price, and status so bookings and transactions share the same numbers.',
        'action' => 'Add service',
    ],
    'products' => [
        'title' => 'Products ready to sell, stock ready to watch',
        'description' => 'Register products with a minimum threshold so low-stock warnings arrive on time.',
        'action' => 'Add product',
    ],
    'inventory' => [
        'title' => 'Stock adds up when every movement is logged',
        'description' => 'Record items in and out as they happen — month-end differences get far easier to trace.',
        'action' => 'Log stock movement',
    ],
    'medical_records' => [
        'title' => 'A complete record helps the next visit',
        'description' => 'Write the SOAP notes, treatments, and progress photos while the details are still fresh.',
        'action' => 'Create record',
    ],
    'transactions' => [
        'title' => 'Register open, transactions rolling',
        'description' => 'Create a transaction from services or products, then record payments until it is settled.',
        'action' => 'New transaction',
    ],
    'bookings' => [
        'title' => 'A tidy schedule keeps slots from colliding',
        'description' => 'Pick the patient, service, and assignee — overlapping slots are rejected automatically.',
        'action' => 'New booking',
    ],
    'staff' => [
        'title' => 'A full team, access matched to each role',
        'description' => 'Invite staff and set their clinic role so everyone sees exactly what they need to.',
        'action' => 'Add staff',
    ],
    'central' => [
        'title' => 'Every clinic in a single view',
        'description' => 'Track the clinics joining the platform, then adjust their status whenever needed.',
        'action' => 'Manage clinics',
    ],
];
