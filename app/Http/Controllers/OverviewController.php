<?php

namespace OGame\Http\Controllers;

use Cache;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use OGame\Facades\AppUtil;
use OGame\Models\Highscore;
use OGame\Services\BuildingQueueService;
use OGame\Services\HighscoreService;
use OGame\Services\PlayerService;
use OGame\Services\ResearchQueueService;
use OGame\Services\UnitQueueService;

class OverviewController extends OGameController
{
    /**
     * Shows the overview index page
     *
     * @param PlayerService $player
     * @param BuildingQueueService $building_queue
     * @param ResearchQueueService $research_queue
     * @param UnitQueueService $unit_queue
     * @return View
     * @throws Exception
     */
    public function index(PlayerService $player, BuildingQueueService $building_queue, ResearchQueueService $research_queue, UnitQueueService $unit_queue): View
    {
        $this->setBodyId('overview');

        $planet = $player->planets->current();

        // Parse building queue for this planet
        $build_full_queue = $building_queue->retrieveQueue($planet);
        $build_active = $build_full_queue->getCurrentlyBuildingFromQueue();
        $build_queue = $build_full_queue->getQueuedFromQueue();

        // Parse research queue for this planet
        $research_full_queue = $research_queue->retrieveQueue($planet);
        $research_active = $research_full_queue->getCurrentlyBuildingFromQueue();
        $research_queue = $research_full_queue->getQueuedFromQueue();

        // Parse ship queue for this planet.
        $ship_full_queue = $unit_queue->retrieveQueue($planet);
        $ship_active = $ship_full_queue->getCurrentlyBuildingFromQueue();
        $ship_queue = $ship_full_queue->getQueuedFromQueue();

        // Get total time of all items in queue
        $ship_queue_time_end = $unit_queue->retrieveQueueTimeEnd($planet);
        $ship_queue_time_countdown = 0;
        if ($ship_queue_time_end > 0) {
            $ship_queue_time_countdown = $ship_queue_time_end - (int)Carbon::now()->timestamp;
        }

        $highscoreService = resolve(HighscoreService::class);

        $user_rank = Cache::remember('player-rank-'.$player->getId(), now()->addMinutes(5), function () use ($highscoreService, $player) {
            return $highscoreService->getHighscorePlayerRank($player);
        });

        $max_ranks = Cache::remember('highscore-player-count', now()->addMinutes(5), function () {
            return Highscore::query()->validRanks()->count();
        });

        $user_score =  Cache::remember('player-score-'.$player->getId(), now()->addMinutes(5), function () use ($player) {
            return AppUtil::formatNumber(Highscore::where('player_id', $player->getId())->first()->general ?? 0);
        });

        return view('ingame.overview.index')->with([
            'header_filename' => $player->planets->current()->getPlanetType(),
            'planet_name' => $player->planets->current()->getPlanetName(),
            'planet_diameter' => $player->planets->current()->getPlanetDiameter(),
            'planet_temp_min' => $player->planets->current()->getPlanetTempMin(),
            'planet_temp_max' => $player->planets->current()->getPlanetTempMax(),
            'planet_coordinates' => $player->planets->current()->getPlanetCoordinates()->asString(),
            'user_points' => $user_score,
            'user_rank' => $user_rank,
            'max_rank' => $max_ranks,
            'user_honor_points' => 0, // @TODO
            'build_active' => $build_active,
            'building_count' => $player->planets->current()->getBuildingCount(),
            'max_building_count' => $player->planets->current()->getPlanetFieldMax(),
            'build_queue' => $build_queue,
            'research_active' => $research_active,
            'research_queue' => $research_queue,
            'ship_active' => $ship_active,
            'ship_queue' => $ship_queue,
            'ship_queue_time_countdown' => $ship_queue_time_countdown,
        ]);
    }
}
