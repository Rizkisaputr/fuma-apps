<?php

namespace App\Livewire\Members;

use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['title' => 'Member'])]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $levelFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingMemberId = null;

    public string $name = '';

    public string $address = '';

    public string $gender = '';

    public string $skillLevel = 'pemula';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLevelFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function createMember(): void
    {
        $this->resetMemberForm();
        $this->showForm = true;
    }

    public function editMember(int $memberId): void
    {
        $member = Member::query()->findOrFail($memberId);

        $this->editingMemberId = $member->id;
        $this->name = $member->name;
        $this->address = $member->address ?? '';
        $this->gender = $member->gender ?? '';
        $this->skillLevel = $member->skill_level;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function saveMember(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'address' => ['required', 'string', 'min:5', 'max:1000'],
            'gender' => ['required', Rule::in(['laki-laki', 'perempuan'])],
            'skillLevel' => ['required', Rule::in(['pemula', 'menengah', 'pro'])],
        ], [
            'name.required' => 'Nama member wajib diisi.',
            'name.min' => 'Nama member minimal 2 karakter.',
            'address.required' => 'Alamat member wajib diisi.',
            'address.min' => 'Alamat member minimal 5 karakter.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.in' => 'Jenis kelamin tidak valid.',
            'skillLevel.required' => 'Level member wajib dipilih.',
            'skillLevel.in' => 'Level member tidak valid.',
        ]);

        $member = $this->editingMemberId
            ? Member::query()->findOrFail($this->editingMemberId)
            : new Member;

        $member->fill([
            'name' => trim($validated['name']),
            'address' => trim($validated['address']),
            'gender' => $validated['gender'],
            'skill_level' => $validated['skillLevel'],
        ])->save();

        session()->flash(
            'member-message',
            $this->editingMemberId ? 'Data member berhasil diperbarui.' : 'Member baru berhasil ditambahkan.',
        );

        $this->closeForm();
    }

    public function toggleActive(int $memberId): void
    {
        $member = Member::query()->findOrFail($memberId);
        $member->update(['is_active' => ! $member->is_active]);

        session()->flash(
            'member-message',
            $member->is_active ? 'Member berhasil diaktifkan.' : 'Member berhasil dinonaktifkan.',
        );
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetMemberForm();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'levelFilter', 'statusFilter']);
        $this->resetPage();
    }

    private function resetMemberForm(): void
    {
        $this->reset(['editingMemberId', 'name', 'address', 'gender']);
        $this->skillLevel = 'pemula';
        $this->resetValidation();
    }

    public function render(): View
    {
        $members = Member::query()
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $query->where('name', 'like', '%'.trim($this->search).'%');
            })
            ->when(in_array($this->levelFilter, ['pemula', 'menengah', 'pro'], true), function (Builder $query): void {
                $query->where('skill_level', $this->levelFilter);
            })
            ->when(in_array($this->statusFilter, ['active', 'inactive'], true), function (Builder $query): void {
                $query->where('is_active', $this->statusFilter === 'active');
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.members.index', [
            'members' => $members,
            'hasFilters' => trim($this->search) !== '' || $this->levelFilter !== '' || $this->statusFilter !== '',
        ]);
    }
}
