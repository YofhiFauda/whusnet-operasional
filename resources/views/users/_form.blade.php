@php
    $isEdit = isset($user);
    $selectedPopIds = collect(old('pop_ids', isset($user) ? $user->pops->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label for="name" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Nama</label>
        <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="phone" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Phone</label>
        <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="status" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Status</label>
        <select id="status" name="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="active" @selected(old('status', $user->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $user->status ?? 'active') === 'inactive')>Nonaktif</option>
        </select>
        @error('status')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="role_id" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Role</label>
        <select id="role_id" name="role_id" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="">Pilih Role</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id ?? '') === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        @error('role_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="password" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">{{ $isEdit ? 'Password Baru' : 'Password' }}</label>
        <input id="password" name="password" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="password_confirmation" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">{{ $isEdit ? 'Konfirmasi Password Baru' : 'Konfirmasi Password' }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
    </div>
</div>

<div class="border-t border-slate-200 pt-4">
    <div class="mb-3">
        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Assign POP/Cabang</label>
        <p class="mt-1 text-xs text-slate-400">Kosongkan jika user belum perlu dibatasi POP tertentu. Owner/Admin tetap full-access.</p>
    </div>

    <div class="grid gap-2 md:grid-cols-2">
        @foreach($pops as $pop)
            <label class="flex items-start gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                <input type="checkbox" name="pop_ids[]" value="{{ $pop->id }}" @checked(in_array($pop->id, $selectedPopIds, true)) class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                <span class="block">
                    <span class="block font-medium text-slate-800">{{ $pop->name }}</span>
                    <span class="block text-xs text-slate-500">{{ $pop->code }} · {{ ucfirst(str_replace('_', ' ', $pop->type)) }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('pop_ids')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    @error('pop_ids.*')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
