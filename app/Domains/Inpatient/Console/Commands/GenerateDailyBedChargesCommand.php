<?php

namespace App\Domains\Inpatient\Console\Commands;

use App\Domains\Inpatient\Actions\GenerateDailyBedChargesAction;
use Illuminate\Console\Command;

class GenerateDailyBedChargesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inpatient:generate-daily-bed-charges {--date= : The date to bill for in YYYY-MM-DD format (defaults to current date)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates and posts midnight bed & board accommodation charges to active inpatient admissions';

    /**
     * Execute the console command.
     */
    public function handle(GenerateDailyBedChargesAction $action): int
    {
        $date = $this->option('date');
        $this->info('Executing midnight bed & board billing engine'.($date ? " for {$date}" : '').'...');

        $result = $action->execute($date);

        if (! empty($result['details'])) {
            $this->table(
                ['Admission #', 'Patient Name', 'Status', 'Date'],
                $result['details']
            );
        }

        $this->info("✓ Inpatient billing complete: {$result['billed_count']} admission(s) charged TZS ".number_format($result['total_amount'], 2)." ({$result['skipped_count']} already billed/skipped).");

        return self::SUCCESS;
    }
}
