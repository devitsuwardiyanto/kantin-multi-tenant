@props(['status' => 'default'])
@php
$map = [
    'active' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    'suspended' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    'default' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
];
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', $map[$status] ?? $map['default']]) }}>{{ $slot }}</span>
