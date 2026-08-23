<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Exceptions\SchedulingConflictException;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\ProviderSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookAppointmentAction
{
    public function execute(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $scheduledTime = Carbon::parse($data['scheduled_time']);

            // Check Provider Schedule if a provider is selected
            if (! empty($data['provider_id'])) {
                $this->validateProviderAvailability($data['provider_id'], $scheduledTime, $data['duration_minutes'] ?? 15);
            }

            return Appointment::create([
                'patient_id' => $data['patient_id'],
                'facility_id' => $data['facility_id'],
                'department_id' => $data['department_id'] ?? null,
                'provider_id' => $data['provider_id'] ?? null,
                'scheduled_time' => $scheduledTime,
                'duration_minutes' => $data['duration_minutes'] ?? 15,
                'appointment_type' => $data['appointment_type'],
                'notes' => $data['notes'] ?? null,
                'status' => 'Scheduled',
            ]);
        });
    }

    protected function validateProviderAvailability(string $providerId, Carbon $time, int $duration): void
    {
        $dayOfWeek = $time->dayOfWeek; // 0 (Sunday) to 6 (Saturday)

        $schedule = ProviderSchedule::where('provider_id', $providerId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (! $schedule) {
            throw SchedulingConflictException::outsideSchedule($providerId, $time->toTimeString());
        }

        $timeString = $time->format('H:i:s');
        if ($timeString < $schedule->start_time || $timeString > $schedule->end_time) {
            throw SchedulingConflictException::outsideSchedule($providerId, $timeString);
        }

        // Check for double booking
        $endTime = $time->copy()->addMinutes($duration);

        $conflict = Appointment::where('provider_id', $providerId)
            ->whereIn('status', ['Scheduled', 'Confirmed'])
            ->where(function ($query) use ($time, $endTime) {
                $query->whereBetween('scheduled_time', [$time, $endTime]);
                if (DB::getDriverName() === 'pgsql') {
                    $query->orWhereRaw('? BETWEEN scheduled_time AND (scheduled_time + (duration_minutes || \' minutes\')::interval)', [$time]);
                } else {
                    $query->orWhereRaw('? BETWEEN scheduled_time AND datetime(scheduled_time, "+" || duration_minutes || " minutes")', [$time]);
                }
            })
            ->exists();

        if ($conflict) {
            throw SchedulingConflictException::doubleBooking($providerId, $time->toDateTimeString());
        }
    }
}
