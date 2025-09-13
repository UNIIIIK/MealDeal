import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:provider/provider.dart';
import 'package:fl_chart/fl_chart.dart';
import '../auth/auth_service.dart';

class AnalyticsScreen extends StatefulWidget {
  const AnalyticsScreen({super.key});

  @override
  State<AnalyticsScreen> createState() => _AnalyticsScreenState();
}

class _AnalyticsScreenState extends State<AnalyticsScreen> {
  String _period = 'daily'; // daily, monthly, yearly

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthService>(
      builder: (context, authService, child) {
        final providerId = authService.currentUser?.uid;
        
        if (providerId == null) {
          return const Center(child: Text('Not signed in'));
        }

        // Query checked out cart items for this provider's listings
    final query = FirebaseFirestore.instance
            .collection('cart')
            .where('status', whereIn: ['awaiting_pickup','claimed','checked_out'])
            .orderBy('checkout_date', descending: true)
            .limit(100);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Analytics'),
      ),
      body: StreamBuilder<QuerySnapshot<Map<String, dynamic>>>(
        stream: query.snapshots(),
        builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(child: CircularProgressIndicator());
              }

              if (snapshot.hasError) {
                return Center(
                  child: Text('Error: ${snapshot.error}'),
                );
              }

          final Map<String, double> dayToSaved = {};
              final Map<String, int> dayToOrders = {};
              
          if (snapshot.hasData) {
                for (final cartDoc in snapshot.data!.docs) {
                  final cartData = cartDoc.data();
                  final items = cartData['items'] as List<dynamic>? ?? [];
                  
                  // Filter items that belong to this provider
                  for (final item in items) {
                    if (item is Map<String, dynamic>) {
                      final listingId = item['listing_id'] as String?;
                      if (listingId != null) {
                        // For now, we'll process all items and assume they belong to this provider
                        // In a production app, you'd want to verify the provider_id
                         final ts = cartData['checkout_date'] as Timestamp?;
                        if (ts != null) {
              final day = DateTime(ts.toDate().year, ts.toDate().month, ts.toDate().day).toIso8601String();
                          final saved = (item['price'] ?? 0).toDouble() * (item['quantity'] ?? 1);
              dayToSaved[day] = (dayToSaved[day] ?? 0) + saved;
                          dayToOrders[day] = (dayToOrders[day] ?? 0) + 1;
                        }
                      }
                    }
                  }
            }
          }

          if (dayToSaved.isEmpty) {
                return Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.analytics_outlined,
                        size: 64,
                        color: Colors.grey.shade400,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'No checked out orders yet',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: Colors.grey.shade600,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Analytics will appear here once customers checkout',
                        style: TextStyle(
                          color: Colors.grey.shade500,
                          fontSize: 14,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.blue.shade50,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.blue.shade200),
                        ),
                        child: Column(
                          children: [
                            Icon(
                              Icons.info_outline,
                              color: Colors.blue.shade600,
                              size: 24,
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'How to get analytics data:',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: Colors.blue.shade700,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '1. Create food listings\n2. Wait for customers to order\n3. Complete the orders\n4. Analytics will appear here',
                              style: TextStyle(
                                color: Colors.blue.shade600,
                                fontSize: 12,
                              ),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
          }

          // Aggregate based on selected period
          Map<String, double> aggregated = {};
          if (_period == 'daily') {
            aggregated = dayToSaved;
          } else {
            for (final e in dayToSaved.entries) {
              final dt = DateTime.parse(e.key);
              if (_period == 'monthly') {
                final key = DateTime(dt.year, dt.month).toIso8601String();
                aggregated[key] = (aggregated[key] ?? 0) + e.value;
              } else {
                final key = DateTime(dt.year).toIso8601String();
                aggregated[key] = (aggregated[key] ?? 0) + e.value;
              }
            }
          }

          final entries = aggregated.entries.toList()
            ..sort((a, b) => a.key.compareTo(b.key));

          final maxVal = entries.map((e) => e.value).fold<double>(0, (p, c) => c > p ? c : p);
          final totalOrders = dayToOrders.values.fold(0, (sum, count) => sum + count);
          final totalSaved = dayToSaved.values.fold(0.0, (sum, value) => sum + value);

          return Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Header with statistics
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [Colors.blue.shade50, Colors.green.shade50],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.grey.shade200),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                          Text(
                            'Analytics Overview',
                            style: TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.bold,
                              color: Colors.grey.shade800,
                            ),
                          ),
                          const SizedBox(height: 16),
                      Row(
                        children: [
                          Expanded(
                            child: _buildStatCard(
                              'Total Orders',
                              totalOrders.toString(),
                              Icons.shopping_cart,
                              Colors.blue,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _buildStatCard(
                              'Total Saved',
                              '₱${totalSaved.toStringAsFixed(0)}',
                              Icons.savings,
                              Colors.green,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                
                // Period filter chips
                Row(
                  children: [
                    ChoiceChip(
                      label: const Text('Daily'),
                      selected: _period == 'daily',
                      onSelected: (_) => setState(() => _period = 'daily'),
                    ),
                    const SizedBox(width: 8),
                    ChoiceChip(
                      label: const Text('Monthly'),
                      selected: _period == 'monthly',
                      onSelected: (_) => setState(() => _period = 'monthly'),
                    ),
                    const SizedBox(width: 8),
                    ChoiceChip(
                      label: const Text('Yearly'),
                      selected: _period == 'yearly',
                      onSelected: (_) => setState(() => _period = 'yearly'),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                
                // Chart title
                Text(
                  _period == 'daily' ? 'Daily Food Savings' : _period == 'monthly' ? 'Monthly Food Savings' : 'Yearly Food Savings',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.grey.shade800,
                  ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                  'Amount saved by customers through your deals',
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.grey.shade600,
                  ),
                ),
                const SizedBox(height: 20),
                
                // Line Chart
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.grey.shade200,
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: LineChart(
                      LineChartData(
                            gridData: FlGridData(
                              show: true, 
                              horizontalInterval: maxVal > 0 ? maxVal / 5 : 1,
                              verticalInterval: 1,
                            ),
                        titlesData: FlTitlesData(
                              leftTitles: AxisTitles(
                                sideTitles: SideTitles(
                                  showTitles: true, 
                                  reservedSize: 50,
                                  getTitlesWidget: (value, meta) {
                                    return Text(
                                      '₱${value.toInt()}',
                                      style: const TextStyle(fontSize: 10),
                                    );
                                  },
                                ),
                              ),
                              bottomTitles: AxisTitles(
                                sideTitles: SideTitles(
                                  showTitles: true,
                                  getTitlesWidget: (value, meta) {
                                    if (value.toInt() < entries.length) {
                                      final date = DateTime.parse(entries[value.toInt()].key);
                                      if (_period == 'daily') {
                                        return Text(
                                          '${date.day}/${date.month}',
                                          style: const TextStyle(fontSize: 10),
                                        );
                                      } else if (_period == 'monthly') {
                                        return Text(
                                          '${date.month}/${date.year}',
                                          style: const TextStyle(fontSize: 10),
                                        );
                                      } else {
                                        return Text(
                                          '${date.year}',
                                          style: const TextStyle(fontSize: 10),
                                        );
                                      }
                                    }
                                    return const Text('');
                                  },
                                ),
                              ),
                          rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                          topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                        ),
                        borderData: FlBorderData(show: true),
                        minY: 0,
                            maxY: maxVal > 0 ? maxVal * 1.1 : 100,
                        lineBarsData: [
                          LineChartBarData(
                            isCurved: true,
                            color: Colors.green.shade600,
                            barWidth: 3,
                                dotData: FlDotData(
                                  show: true,
                                  getDotPainter: (spot, percent, barData, index) {
                                    return FlDotCirclePainter(
                                      radius: 4,
                                      color: Colors.green.shade600,
                                      strokeWidth: 2,
                                      strokeColor: Colors.white,
                                    );
                                  },
                                ),
                            spots: [
                              for (int i = 0; i < entries.length; i++)
                                FlSpot(i.toDouble(), entries[i].value),
                            ],
                          ),
                        ],
                      ),
                        ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
        );
      },
    );
  }

  // Helper method to build statistic cards
  Widget _buildStatCard(String title, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.3)),
        boxShadow: [
          BoxShadow(
            color: color.withValues(alpha: 0.1),
            blurRadius: 4,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: color, size: 20),
              const SizedBox(width: 8),
              Text(
                title,
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.grey.shade600,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            value,
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}