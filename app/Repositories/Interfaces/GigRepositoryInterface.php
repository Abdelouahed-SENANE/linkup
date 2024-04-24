<?php
namespace App\Repositories\Interfaces;

use App\Dto\GigDto;
use App\Models\Freelancer;
use App\Models\Gig;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface GigRepositoryInterface {
    public function all() : JsonResponse;
    public function createGig(GigDto $gigDto , Freelancer $freelancer);
    public function updateGig(int $gigId , GigDto $gigDto) : Gig;
    public function deleteGig(Gig $gig);
    public function updateStatus($gigId, $status);
    public function myGigs() : JsonResponse;
    public function getGigWithCheckOrderByClient(Gig $gig , $clientId);
    public function getActiveGigs($query);
    public function getPendingGigs();
    public function getPopularGigOnWeek();
    public function getSalesBydDayOfWeek();
    public function countGigs();
    public function getGigReviwes($gigId);
}
