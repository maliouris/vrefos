<div>
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">Edit Baby Action</h1>
        <x-mary-button
            label="Delete"
            icon="o-trash"
            class="btn-error btn-outline btn-sm"
            wire:click="promptDelete"
        />
    </div>

    @include('livewire.pages.baby-action.form', ['submit' => 'update'])

    <x-confirm-delete
        :show="$showDeleteModal"
        title="Delete this action?"
        message="This cannot be undone."
        confirm="delete"
    />
</div>
