<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Services\PdfBadgeService;
use Illuminate\Console\Command;

class TestPdfGeneration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:pdf-generation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test PDF badge generation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = app(PdfBadgeService::class);
        $participant = Participant::where('status', 'accepted')->first();

        if (!$participant) {
            $this->error('No accepted participants found');
            return 1;
        }

        $this->info('Testing PDF generation for: ' . $participant->full_name);

        try {
            $pdf = $service->generateBadge($participant);
            $this->info('PDF generated successfully, size: ' . strlen($pdf) . ' bytes');
            return 0;
        } catch (\Exception $e) {
            $this->error('PDF generation failed: ' . $e->getMessage());
            return 1;
        }
    }
}
