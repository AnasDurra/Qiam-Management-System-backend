<?php

namespace App\Domain\Models\CD;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $fillable = ['work_day_id', 'status_id', 'customer_id', 'start_time', 'end_time', 'cancellation_reason'];

    public function workDay(): BelongsTo
    {
        return $this->belongsTo(WorkDay::class, 'work_day_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AppointmentStatus::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function caseNote(): HasOne
    {
        return $this->hasOne(CaseNote::class);
    }

    /**
     * mutator to return whether the appointment is reserved
     * or not. by checking status_id == 5 && customer_id != null
     */
    public function getIsReservedAttribute(): bool
    {
        return $this->status_id == AppointmentStatus::STATUS_RESERVED && $this->customer_id != null;
    }

    /**
     * mutator to return whether the appointment is in the future or not
     * by checking date of appointment's work day is greater than today
     */
    public function getIsFutureAttribute(): bool
    {
        return $this->workDay->day_date > now();
    }

    /**
     * mutator to return whether the appointment is already cancelled or not
     * by checking status_id == 1 || status_id == 2 || status_id == 3
     */
    public function getIsCancelledAttribute(): bool
    {
        return $this->status_id == AppointmentStatus::STATUS_CANCELED_BY_CONSULTANT ||
            $this->status_id == AppointmentStatus::STATUS_CANCELED_BY_EMPLOYEE ||
            $this->status_id == AppointmentStatus::STATUS_CANCELED_BY_CONSULTANT;
    }

    /**
     * mutator to return consultant id for the appointment
     */
    public function getConsultantId(): int
    {
        $consultant = $this->workDay->shift->consultant;

        $consultantID = $consultant->id;
        $this->unsetRelation('workDay');

        return $consultantID;
    }

    /**
     * mutator to return clinic name for the appointment
     */
    public function getClinicName(): string|null
    {
        $clinic = $this->workDay->shift->consultant->clinic;

        $clinicName = $clinic->name;
        $this->unsetRelation('workDay');

        return $clinicName;
    }

}
