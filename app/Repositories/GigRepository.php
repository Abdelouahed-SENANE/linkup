<?php

namespace App\Repositories;

use App\Dto\GigDto;
use App\Models\Freelancer;
use App\Models\Gig;
use App\Models\Order;
use App\Models\Rating;
use App\Models\User;
use App\Repositories\Interfaces\GigRepositoryInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

use function Laravel\Prompts\select;

class GigRepository implements GigRepositoryInterface
{

   /**
    * @var Gig
    */
   protected $gig;

   /**
    * @param gig $gig
    */

   public function __construct(Gig $gig)
   {
      $this->gig = $gig;
   }
   // ========== Implentation Interfaces =========
   public function all(): JsonResponse
   {
      $gigs = $this->gig->all();
      return response()->json([$gigs]);
   }
   public function myGigs(): JsonResponse
   {
      $user = JWTAuth::user();
      $freelancer = $user->freelancer()->first();
      $myGigs = Gig::where('freelancer_id', $freelancer->id)->get();
      $activeGig = [];
      $pendingGig = [];
      $deniedGig = [];

      foreach ($myGigs as $myGig) {

         $formattedGig = [
            'id' => $myGig->id,
            'title' => $myGig->title,
            'excerpt' => $myGig->excerpt,
            'delivery_date' => $myGig->delivery,
            'status' => $myGig->status,
         ];
         if ($myGig->status === 'approved') {
            $activeGig[] = $formattedGig;
         } else if ($myGig->status === 'pending') {
            $formattedGig['actions'] = 'actions';
            $pendingGig[] = $formattedGig;
         } else {
            $deniedGig[] = $formattedGig;
         }
      }
      $tabs = [
         [
            'id' => 1,
            'label' => 'Active',
            'tableHead' => ['ID', 'title', 'Excerpt', 'Delivery date', 'Status'],
            'rows' => $activeGig,
         ],
         [
            'id' => 2,
            'label' => 'Pending approval',
            'tableHead' => ['ID', 'title', 'Excerpt', 'Delivery date', 'Status', 'actions'],
            'rows' => $pendingGig,
         ],
         [
            'id' => 3,
            'label' => 'Denied',
            'tableHead' => ['ID', 'title', 'Excerpt', 'Delivery date', 'Status'],
            'rows' => $deniedGig,
         ],
      ];


      return response()->json([
         'myGigs' => $tabs
      ]);
   }
   public function createGig(GigDto $gigDto, Freelancer $freelancer)
   {
      $attributes = $gigDto->toArray();
      return $freelancer->gigs()->create($attributes);
   }

   public function updateGig($gigId, GigDto $gigDto): Gig
   {
      $gig = Gig::findOrFail($gigId);
      $attributes = $gigDto->toArray();
      $gig->update($attributes);
      return $gig;
   }

   public function deleteGig(Gig $gig)
   {
      return $gig->delete($gig);
   }
   public function updateStatus($gigId, $status)
   {
      $gig = $this->gig::findOrFail($gigId);
      $gig->status = $status;
      $gig->save();
      return $gig;
   }
   public function getGigWithCheckOrderByClient(Gig $gig, $clientId)
   {
      $query = $this->gig->with(['freelancer.user:id,name,picture'])
         ->where('id', $gig->id);

      // Check if the client is authenticated
      if ($clientId !== null) {
         $query->with(['orders' => function ($query) use ($clientId) {
            $query->where('client_id', $clientId)
               ->where('payment_status', 'PAID');
         }]);
      }
      $foundGig = $query->first();
      return $foundGig;
   }
   public function getActiveGigs($request)
   {
      $subcategory = $request->input('subcategory');
      $searchTerm = $request->input('search');
      $delivery_time = $request->input('delivery');
      $min_price = $request->input('minPrice');
      $max_price = $request->input('maxPrice');

      $query = Gig::query();

      $query->with(['freelancer.user:id,name,picture', 'subcategory:id,name']);

      $query->where('status', 'approved');

      if ($delivery_time) {
         $query->where('delivery', $delivery_time);
      }
      if ($searchTerm) {
         $lowercaseSearchTerm = strtolower($searchTerm);
         $query->whereRaw("LOWER(title) LIKE ?", ['%' . $lowercaseSearchTerm . '%']);
      }


      if ($min_price && $max_price) {
         $query->whereBetween('price', [$min_price, $max_price]);
      }
      if ($subcategory) {
         $query->whereHas('subcategory', function ($query) use ($subcategory) {
            $query->where('name', $subcategory);
         });
      }
      $result = $query->paginate(9);
      return $result;
   }

   public function getPendingGigs()
   {
      $pendingGigs = $this->gig->with('freelancer.user:id,name,picture')->where('status', 'pending')->get();
      return $pendingGigs;
   }

   public function getPopularGigOnWeek()
   {
      $startDate = now()->subDays(7)->startOfWeek();

      $chartData = [];

      $gigIds = Order::where('created_at', '>=', $startDate)
         ->where('payment_status', 'PAID')
         ->pluck('gig_id')
         ->unique()
         ->toArray();

      $daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

      foreach ($gigIds as $gigId) {
         $gigTitle = Gig::find($gigId)->title;

         $orderCountsByDay = array_fill_keys($daysOfWeek, 0);

         $orders = Order::where('gig_id', $gigId)
            ->where('created_at', '>=', $startDate)
            ->where('payment_status', 'PAID')
            ->get();

         foreach ($orders as $order) {
            $dayOfWeek = $order->created_at->format('D');
            if (in_array($dayOfWeek, $daysOfWeek)) {
               $orderCountsByDay[$dayOfWeek]++;
            }
         }

         $chartData[] = [
            'title' => $gigTitle,
            'data' => array_values($orderCountsByDay),
         ];
      }

      return $chartData;
   }
   public function getSalesBydDayOfWeek()
   {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $salesData = DB::table('orders')
            ->join('gigs', 'orders.gig_id', '=', 'gigs.id')
            ->select(
                DB::raw('DATE(orders.created_at) AS date'),
                DB::raw('SUM(gigs.price) AS total_sales')
            )
            ->whereBetween('orders.created_at', [$startOfWeek, $endOfWeek])
            ->where('orders.payment_status', 'PAID')
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->orderBy(DB::raw('DATE(orders.created_at)'))
            ->get();
        $formattedSalesData = [];
        foreach ($salesData as $sale) {
            $formattedSalesData[$sale->date] = $sale->total_sales;
        }

        return $formattedSalesData;
   }



   public function countGigs()
   {
      return $this->gig->all()->count();
   }
   public function getAllReviewsByGigId($gigId)
   {
      $gig = $this->gig->find($gigId);
      if (!$gig) {
         return [];
      }
      $ordersIds = $gig->orders()->pluck('id')->toArray();
      $allReviews = [];
      foreach ($ordersIds as $orderId) {
         $ratings = Rating::with('client.user:id,name,picture')
         ->where('order_id', $orderId)
         ->latest('created_at')      
         ->get();
         
         $allReviews = array_merge($allReviews , $ratings->all());
      }
      return $allReviews;
   }

   public function getLastGigs()
   {
      $recentGigs = $this->gig->with('subcategory' , 'freelancer.user')->orderBy('created_at' , 'desc')->limit(3)->get();
      return $recentGigs;
   }
}
