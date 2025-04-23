<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;

class TrafficController extends Controller
{
    public function getTraffic(Request $request)
    {
        $lat = $request->query('lat');
        $lon = $request->query('lon');
        $radius = $request->query('radius', 16093); // 10 miles in meters
        $apiKey = config('services.tomtom.key');

        $response = Http::get('https://api.tomtom.com/traffic/services/4/incidentDetails', [
            'key' => $apiKey,
            'bbox' => ($lat - 0.1) . ',' . ($lon - 0.1) . ',' . ($lat + 0.1) . ',' . ($lon + 0.1), // Approx 10-mile radius
            'fields' => '{incidents{type,geometry{type,coordinates},properties{id,iconCategory,magnitudeOfDelay,events{description,code},startTime,endTime,delay}}}',
        ]);

        return $response->json();
    }
}
