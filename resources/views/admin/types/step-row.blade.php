<div class="wf-step fieldset" style="padding:12px 14px">
    <div class="form-grid" style="grid-template-columns: 1.4fr 1fr 1fr auto;align-items:end">
        <x-field label="Stage name"><input name="steps[{{ $i }}][name]" class="input input-sm" value="{{ $s?->name }}" required></x-field>
        <x-field label="Action"><select name="steps[{{ $i }}][action_type]" class="select input-sm">@foreach(\App\Models\ApprovalStep::ACTIONS as $k => $v)<option value="{{ $k }}" @selected($s?->action_type === $k)>{{ $v }}</option>@endforeach</select></x-field>
        <x-field label="Role"><select name="steps[{{ $i }}][role_id]" class="select input-sm">@foreach($roles as $r)<option value="{{ $r->id }}" @selected($s?->role_id === $r->id)>{{ $r->name }}</option>@endforeach</select></x-field>
        <button type="button" class="btn btn-ghost btn-xs" data-remove-step aria-label="Remove stage"><x-icon name="x" size="sm"/></button>
    </div>
    <label class="check mt-1 xs"><input type="hidden" name="steps[{{ $i }}][requires_documents_complete]" value="0"><input type="checkbox" name="steps[{{ $i }}][requires_documents_complete]" value="1" @checked($s?->requires_documents_complete)> Required documents must be verified before this stage can approve/forward</label>
</div>
