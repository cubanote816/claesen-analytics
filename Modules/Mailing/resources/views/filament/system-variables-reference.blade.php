@php
$systemVars = [
    ['token' => '{{ name }}',            'label' => __('mailing::resource.system_vars.name_label'),        'example' => __('mailing::resource.system_vars.name_example')],
    ['token' => '{{ regio }}',           'label' => __('mailing::resource.system_vars.regio_label'),       'example' => __('mailing::resource.system_vars.regio_example')],
    ['token' => '{{ unsubscribe_url }}', 'label' => __('mailing::resource.system_vars.unsubscribe_label'), 'example' => __('mailing::resource.system_vars.unsubscribe_example')],
];
@endphp

{{-- Static reference panel: tokens always available in every template --}}
<div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden text-sm">
    <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 font-medium text-gray-700 dark:text-gray-300">
        {{ __('mailing::resource.system_vars.panel_title') }}
    </div>
    <table class="w-full">
        <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                <th class="text-left px-4 py-2 w-1/3">{{ __('mailing::resource.fields.variable_key') }}</th>
                <th class="text-left px-4 py-2 w-1/3">{{ __('mailing::resource.fields.variable_label') }}</th>
                <th class="text-left px-4 py-2 w-1/3">{{ __('mailing::resource.fields.variable_example') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach ($systemVars as $var)
            <tr class="bg-white dark:bg-gray-900">
                <td class="px-4 py-2 font-mono text-indigo-600 dark:text-indigo-400 whitespace-nowrap">{{ $var['token'] }}</td>
                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $var['label'] }}</td>
                <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $var['example'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
