@use('App\Icons\Ios')
@use('App\Icons\Android')

{{-- A failed mutation, shown beside the action instead of replacing the
     screen, so a half-filled form keeps what was typed. --}}
@if ($message !== null)
    <native:row class="w-full items-start gap-2 rounded-lg bg-theme-error-container p-3">
        <native:icon :ios="Ios::ExclamationmarkTriangleFill" :android="Android::ErrorOutline" :size="18"
            class="text-theme-on-error-container" />
        <native:text class="flex-1 text-sm leading-snug text-theme-on-error-container">{{ $message }}</native:text>
    </native:row>
@endif
