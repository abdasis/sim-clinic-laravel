<?php

return [
    'module' => 'module',
    'range' => 'day range',
    'title' => 'Overview',
    'subtitle' => 'Key numbers for this module and how they have been moving.',
    'last_days' => 'Last :days days',
    'last_weeks' => 'Last :weeks weeks',
    'refresh' => 'Refresh stats',
    'refresh_hint' => 'Fetch the latest numbers from the server',
    'loading' => 'Crunching the numbers...',
    'error_title' => 'Stats are unavailable right now',
    'error_description' => 'The server connection is acting up. Give it another try in a moment.',
    'retry' => 'Try again',
    'empty_title' => 'No activity yet',
    'empty_description' => 'As soon as the first records land, this chart starts moving.',
    'chart_hint' => 'Hover the chart to read the value for a specific date.',

    'trend' => [
        'patients_new' => 'New patients per day',
        'service_sales' => 'Services sold per day',
        'product_movements' => 'Stock movements per day',
        'inventory_net' => 'Net stock change per day',
        'medical_records_new' => 'New medical records per day',
        'transactions_revenue' => 'Paid revenue per day',
        'bookings_new' => 'Bookings created per day',
        'staff_new' => 'Staff joined per day',
        'tenants_new' => 'New clinics per week',
    ],

    'patients' => [
        'total' => 'Total patients',
        'new_7d' => 'New in 7 days',
        'new_30d' => 'New in 30 days',
    ],
    'services' => [
        'active' => 'Active services',
        'archived' => 'Archived services',
        'avg_price' => 'Average price',
    ],
    'products' => [
        'total' => 'Total products',
        'low_stock' => 'Low stock',
        'archived' => 'Archived products',
    ],
    'inventory' => [
        'movements' => 'Stock movements',
        'total_in' => 'Stock in',
        'total_out' => 'Stock out',
    ],
    'medical_records' => [
        'total' => 'Total records',
        'this_week' => 'Created this week',
        'authors' => 'Active authors',
    ],
    'transactions' => [
        'revenue' => 'Paid revenue',
        'outstanding' => 'Outstanding',
        'paid_count' => 'Paid transactions',
        'cancelled_count' => 'Cancelled transactions',
    ],
    'bookings' => [
        'today' => 'Bookings today',
        'this_week' => 'Bookings this week',
        'pending' => 'Awaiting confirmation',
        'done' => 'Completed',
    ],
    'staff' => [
        'total' => 'Total staff',
        'active' => 'Active staff',
        'roles_filled' => 'Roles filled',
    ],
    'central' => [
        'total' => 'Total clinics',
        'active' => 'Active clinics',
        'inactive' => 'Inactive clinics',
        'new_this_month' => 'Joined this month',
    ],
];
