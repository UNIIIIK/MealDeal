import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:latlong2/latlong.dart' as ll;

class RoutingService {
  // OSRM API endpoint for routing
  static const String _osrmBaseUrl = 'https://router.project-osrm.org/route/v1';
  
  // Alternative: Google Directions API (requires API key)
  // static const String _googleDirectionsUrl = 'https://maps.googleapis.com/maps/api/directions/json';

  /// Get route between two points using OSRM API
  static Future<List<ll.LatLng>> getRoute(
    ll.LatLng origin,
    ll.LatLng destination, {
    String profile = 'driving', // driving, walking, cycling
  }) async {
    try {
      final url = Uri.parse(
        '$_osrmBaseUrl/$profile/${origin.longitude},${origin.latitude};${destination.longitude},${destination.latitude}?overview=full&geometries=geojson'
      );

      final response = await http.get(
        url,
        headers: {
          'User-Agent': 'MealDealApp/1.0',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        
        if (data['code'] == 'Ok' && data['routes'] != null && data['routes'].isNotEmpty) {
          final route = data['routes'][0];
          final geometry = route['geometry'];
          
          if (geometry['type'] == 'LineString' && geometry['coordinates'] != null) {
            final coordinates = geometry['coordinates'] as List;
            return coordinates.map((coord) {
              return ll.LatLng(
                (coord[1] as num).toDouble(), // latitude
                (coord[0] as num).toDouble(), // longitude
              );
            }).toList();
          }
        }
      }
    } catch (e) {
      print('Error getting route from OSRM: $e');
    }

    // Fallback: return direct line if API fails
    return [origin, destination];
  }

  /// Get route with multiple waypoints
  static Future<List<ll.LatLng>> getRouteWithWaypoints(
    List<ll.LatLng> waypoints, {
    String profile = 'driving',
  }) async {
    if (waypoints.length < 2) return waypoints;

    try {
      final waypointString = waypoints.map((point) => '${point.longitude},${point.latitude}').join(';');
      final url = Uri.parse(
        '$_osrmBaseUrl/$profile/$waypointString?overview=full&geometries=geojson'
      );

      final response = await http.get(
        url,
        headers: {
          'User-Agent': 'MealDealApp/1.0',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        
        if (data['code'] == 'Ok' && data['routes'] != null && data['routes'].isNotEmpty) {
          final route = data['routes'][0];
          final geometry = route['geometry'];
          
          if (geometry['type'] == 'LineString' && geometry['coordinates'] != null) {
            final coordinates = geometry['coordinates'] as List;
            return coordinates.map((coord) {
              return ll.LatLng(
                (coord[1] as num).toDouble(),
                (coord[0] as num).toDouble(),
              );
            }).toList();
          }
        }
      }
    } catch (e) {
      print('Error getting route with waypoints from OSRM: $e');
    }

    // Fallback: return direct line through waypoints
    return waypoints;
  }

  /// Get route distance and duration
  static Future<Map<String, dynamic>?> getRouteInfo(
    ll.LatLng origin,
    ll.LatLng destination, {
    String profile = 'driving',
  }) async {
    try {
      final url = Uri.parse(
        '$_osrmBaseUrl/$profile/${origin.longitude},${origin.latitude};${destination.longitude},${destination.latitude}?overview=false'
      );

      final response = await http.get(
        url,
        headers: {
          'User-Agent': 'MealDealApp/1.0',
          'Accept': 'application/json',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        
        if (data['code'] == 'Ok' && data['routes'] != null && data['routes'].isNotEmpty) {
          final route = data['routes'][0];
          return {
            'distance': route['distance']?.toDouble() ?? 0.0, // meters
            'duration': route['duration']?.toDouble() ?? 0.0, // seconds
          };
        }
      }
    } catch (e) {
      print('Error getting route info from OSRM: $e');
    }

    return null;
  }

  /// Get walking route (optimized for pedestrians)
  static Future<List<ll.LatLng>> getWalkingRoute(
    ll.LatLng origin,
    ll.LatLng destination,
  ) async {
    return getRoute(origin, destination, profile: 'walking');
  }

  /// Get driving route (optimized for cars)
  static Future<List<ll.LatLng>> getDrivingRoute(
    ll.LatLng origin,
    ll.LatLng destination,
  ) async {
    return getRoute(origin, destination, profile: 'driving');
  }

  /// Get cycling route (optimized for bicycles)
  static Future<List<ll.LatLng>> getCyclingRoute(
    ll.LatLng origin,
    ll.LatLng destination,
  ) async {
    return getRoute(origin, destination, profile: 'cycling');
  }

  /// Format distance for display
  static String formatDistance(double distanceInMeters) {
    if (distanceInMeters < 1000) {
      return '${distanceInMeters.round()} m';
    } else {
      final km = distanceInMeters / 1000;
      return '${km.toStringAsFixed(1)} km';
    }
  }

  /// Format duration for display
  static String formatDuration(double durationInSeconds) {
    final minutes = (durationInSeconds / 60).round();
    if (minutes < 60) {
      return '$minutes min';
    } else {
      final hours = minutes ~/ 60;
      final remainingMinutes = minutes % 60;
      if (remainingMinutes == 0) {
        return '$hours hr';
      } else {
        return '$hours hr $remainingMinutes min';
      }
    }
  }

  /// Check if route is within reasonable distance (for Bogo City)
  static bool isRouteReasonable(double distanceInMeters) {
    // Consider routes up to 10km as reasonable within Bogo City
    return distanceInMeters <= 10000;
  }
}
