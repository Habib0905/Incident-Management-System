<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Services\SimilarityService;
use Illuminate\Console\Command;

class ComputeIncidentEmbeddings extends Command
{
    protected $signature = 'incidents:compute-embeddings';

    protected $description = 'Compute embedding vectors for all incidents without embeddings';

    public function handle(SimilarityService $similarity)
    {
        $incidents = Incident::whereNull('embedding')->get();
        $total = $incidents->count();

        $this->info("Found {$total} incidents without embeddings.");

        if ($total === 0) {
            $this->info('Nothing to do.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $failed = 0;

        foreach ($incidents as $incident) {
            try {
                $text = $similarity->buildIncidentText($incident);
                $embedding = $similarity->computeEmbedding($text);
                $incident->update([
                    'embedding' => $embedding,
                    'last_embedded_at' => now(),
                ]);
            } catch (\Exception $e) {
                $this->warn("\nFailed for incident #{$incident->id}: {$e->getMessage()}");
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $success = $total - $failed;
        $this->info("Done. {$success} embeddings computed, {$failed} failed.");

        return Command::SUCCESS;
    }
}
