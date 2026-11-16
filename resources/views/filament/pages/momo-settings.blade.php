<x-filament-panels::page>
    {{-- Trang được bảo vệ bằng canAccessSettingsPage(); submit qua nhwin/settings. --}}
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-check">
            Lưu cấu hình MoMo
        </x-filament::button>
    </form>
</x-filament-panels::page>
