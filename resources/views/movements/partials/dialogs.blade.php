<dialog class="modal" id="depart-dialog">
    <form method="POST" action="">@csrf
        <div class="modal-head"><x-icon name="depart"/><h3>Record actual departure</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body stack">
            <div class="small">Member: <b data-field="who"></b></div>
            <p class="small muted mb-0">Verify identity and authorization before recording. Planned dates are not changed.</p>
            <x-field label="Actual departure time" required><input type="datetime-local" name="actual_at" class="input" value="{{ now()->format('Y-m-d\TH:i') }}" max="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}" required></x-field>
            <x-field label="Remarks"><input name="remarks" class="input" maxlength="255" placeholder="Optional"></x-field>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-primary"><x-icon name="check" size="sm"/> Record departure</button></div>
    </form>
</dialog>
<dialog class="modal" id="return-dialog">
    <form method="POST" action="">@csrf
        <div class="modal-head"><x-icon name="arrive"/><h3>Record actual return</h3><button class="x" type="button" data-dialog-close><x-icon name="x"/></button></div>
        <div class="modal-body stack">
            <div class="small">Member: <b data-field="who"></b></div>
            <x-field label="Actual return time" required help="If the return was not recorded on time, enter the actual time it occurred."><input type="datetime-local" name="actual_at" class="input" value="{{ now()->format('Y-m-d\TH:i') }}" max="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}" required></x-field>
            <x-field label="Remarks"><input name="remarks" class="input" maxlength="255" placeholder="Optional"></x-field>
        </div>
        <div class="modal-foot"><button class="btn btn-secondary" type="button" data-dialog-close>Cancel</button><button class="btn btn-success"><x-icon name="check" size="sm"/> Record return</button></div>
    </form>
</dialog>
