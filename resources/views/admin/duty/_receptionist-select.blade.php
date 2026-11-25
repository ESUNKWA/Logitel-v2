{{-- The receptionists a period / a day can be attributed to (DutyController::receptionists()). --}}
<select name="user_id" class="form-select form-select-sm" aria-label="{{ $label }}" required>
    <option value="">Choisir…</option>
    @foreach ($receptionists as $receptionist)
        <option value="{{ $receptionist->id }}" @selected($selected === $receptionist->id)>{{ $receptionist->name }}</option>
    @endforeach
</select>
