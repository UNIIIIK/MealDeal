import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart' as ll;
import 'geo_service.dart';

class LocationService {
  static const ll.LatLng _bogoCenter = ll.LatLng(10.8, 124.1);

  // Get current device location
  static Future<ll.LatLng?> getCurrentLocation() async {
    try {
      // Check if location services are enabled
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        return null;
      }

      // Check location permissions
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          return null;
        }
      }

      if (permission == LocationPermission.deniedForever) {
        return null;
      }

      // Get current position
      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      final location = ll.LatLng(position.latitude, position.longitude);
      
      // Check if location is within Bogo City
      if (GeoService.isLatLngInBogo(location.latitude, location.longitude)) {
        return location;
      } else {
        // If outside Bogo City, return Bogo City center
        return _bogoCenter;
      }
    } catch (e) {
      debugPrint('Error getting current location: $e');
      return _bogoCenter;
    }
  }

  // Get Bogo City center
  static ll.LatLng getBogoCenter() {
    return _bogoCenter;
  }

  // Check if location is within Bogo City
  static bool isInBogoCity(ll.LatLng location) {
    return GeoService.isLatLngInBogo(location.latitude, location.longitude);
  }

  // Get Bogo City bounds for map constraints
  static Map<String, double> getBogoBounds() {
    final bounds = GeoService.getBogoBounds();
    return {
      'south': bounds.south,
      'west': bounds.west,
      'north': bounds.north,
      'east': bounds.east,
    };
  }

  // Calculate distance between two points
  static double calculateDistance(ll.LatLng point1, ll.LatLng point2) {
    return Geolocator.distanceBetween(
      point1.latitude,
      point1.longitude,
      point2.latitude,
      point2.longitude,
    );
  }

  // Get address from coordinates
  static Future<String?> getAddressFromCoordinates(ll.LatLng location) async {
    return await GeoService.reverseGeocode(location.latitude, location.longitude);
  }

  // Search for locations in Bogo City
  static Future<List<Map<String, dynamic>>> searchBogoLocations(String query) async {
    return await GeoService.searchBogoLocations(query);
  }

  // Validate and constrain location to Bogo City
  static ll.LatLng constrainToBogoCity(ll.LatLng location) {
    if (isInBogoCity(location)) {
      return location;
    }
    
    // If outside Bogo City, find the nearest point within Bogo City
    final bounds = GeoService.getBogoBounds();
    
    double constrainedLat = location.latitude;
    double constrainedLng = location.longitude;
    
    // Constrain latitude
    if (constrainedLat < bounds.south) constrainedLat = bounds.south;
    if (constrainedLat > bounds.north) constrainedLat = bounds.north;
    
    // Constrain longitude
    if (constrainedLng < bounds.west) constrainedLng = bounds.west;
    if (constrainedLng > bounds.east) constrainedLng = bounds.east;
    
    return ll.LatLng(constrainedLat, constrainedLng);
  }

  // Get map options constrained to Bogo City
  static MapOptions getBogoConstrainedMapOptions({
    ll.LatLng? initialCenter,
    double initialZoom = 16.0,
    Function(TapPosition, ll.LatLng)? onTap,
  }) {
    return MapOptions(
      initialCenter: initialCenter ?? _bogoCenter,
      initialZoom: initialZoom,
      minZoom: 13.0, // Zoom level that shows Bogo City area
      maxZoom: 20.0,
      onTap: onTap,
    );
  }

  // Show error message for location outside Bogo City
  static void showBogoCityError(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Location must be within Bogo City, Cebu'),
        backgroundColor: Colors.red,
        duration: Duration(seconds: 3),
      ),
    );
  }

  // Show success message for location saved
  static void showLocationSavedMessage(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Location saved successfully!'),
        backgroundColor: Colors.green,
        duration: Duration(seconds: 2),
      ),
    );
  }
} 