import 'dart:convert';
import 'package:http/http.dart' as http;

class GeoService {
  // More precise Bogo City boundaries
  static const _bogoBounds = _BoundingBox(
    south: 10.666,
    west: 124.000,
    north: 11.100,
    east: 124.200,
  );

  // Bogo City center coordinates
  static const _bogoCenter = LatLng(10.8, 124.1);

  static bool isLatLngInBogo(double lat, double lng) {
    return lat >= _bogoBounds.south && lat <= _bogoBounds.north &&
           lng >= _bogoBounds.west && lng <= _bogoBounds.east;
  }

  static bool isAddressLikelyInBogo(String address) {
    final a = address.toLowerCase();
    return a.contains('bogo') && a.contains('cebu');
  }

  static LatLng getBogoCenter() {
    return _bogoCenter;
  }

  static _BoundingBox getBogoBounds() {
    return _bogoBounds;
  }

  static Future<LatLng?> geocode(String query) async {
    try {
      // Add "Bogo City, Cebu, Philippines" to the query to ensure results are in Bogo
      final searchQuery = '$query, Bogo City, Cebu, Philippines';
      final uri = Uri.parse('https://nominatim.openstreetmap.org/search?format=json&q=${Uri.encodeComponent(searchQuery)}&limit=5&bounded=1&viewbox=${_bogoBounds.west},${_bogoBounds.south},${_bogoBounds.east},${_bogoBounds.north}');
      
      final res = await http.get(uri, headers: { 
        'User-Agent': 'MealDealApp/1.0',
        'Accept': 'application/json',
      });
      
      if (res.statusCode == 200) {
        final data = json.decode(res.body);
        if (data is List && data.isNotEmpty) {
          // Find the first result that's actually in Bogo City
          for (final result in data) {
            final lat = double.tryParse(result['lat'] ?? '');
            final lon = double.tryParse(result['lon'] ?? '');
            if (lat != null && lon != null && isLatLngInBogo(lat, lon)) {
              return LatLng(lat, lon);
            }
          }
        }
      }
    } catch (e) {
      print('Geocoding error: $e');
    }
    return null;
  }

  static Future<String?> reverseGeocode(double lat, double lng) async {
    try {
      final uri = Uri.parse('https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng&zoom=18&addressdetails=1');
      
      final res = await http.get(uri, headers: { 
        'User-Agent': 'MealDealApp/1.0',
        'Accept': 'application/json',
      });
      
      if (res.statusCode == 200) {
        final data = json.decode(res.body);
        final displayName = data['display_name'] as String?;
        
        if (displayName != null && isAddressLikelyInBogo(displayName)) {
          return displayName;
        } else {
          // If the address doesn't contain Bogo, create a custom one
          return 'Location in Bogo City, Cebu, Philippines';
        }
      }
    } catch (e) {
      print('Reverse geocoding error: $e');
    }
    return null;
  }

  static Future<List<Map<String, dynamic>>> searchBogoLocations(String query) async {
    try {
      final searchQuery = '$query, Bogo City, Cebu, Philippines';
      final uri = Uri.parse('https://nominatim.openstreetmap.org/search?format=json&q=${Uri.encodeComponent(searchQuery)}&limit=10&bounded=1&viewbox=${_bogoBounds.west},${_bogoBounds.south},${_bogoBounds.east},${_bogoBounds.north}');
      
      final res = await http.get(uri, headers: { 
        'User-Agent': 'MealDealApp/1.0',
        'Accept': 'application/json',
      });
      
      if (res.statusCode == 200) {
        final data = json.decode(res.body);
        if (data is List) {
          return data.where((result) {
            final lat = double.tryParse(result['lat'] ?? '');
            final lon = double.tryParse(result['lon'] ?? '');
            return lat != null && lon != null && isLatLngInBogo(lat, lon);
          }).map((result) => {
            'display_name': result['display_name'],
            'lat': double.parse(result['lat']),
            'lon': double.parse(result['lon']),
          }).toList();
        }
      }
    } catch (e) {
      print('Location search error: $e');
    }
    return [];
  }
}

class _BoundingBox {
  final double south;
  final double west;
  final double north;
  final double east;
  const _BoundingBox({required this.south, required this.west, required this.north, required this.east});
}

class LatLng {
  final double latitude;
  final double longitude;
  const LatLng(this.latitude, this.longitude);
}


