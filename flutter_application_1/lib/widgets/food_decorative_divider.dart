import 'dart:math';
import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class FoodDecorativeDivider extends StatelessWidget {
  final String? text;
  final double height;
  final Color? color;
  final EdgeInsetsGeometry? padding;

  const FoodDecorativeDivider({
    super.key,
    this.text,
    this.height = 1.0,
    this.color,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    final dividerColor = color ?? AppTheme.lightGray;
    
    if (text != null) {
      return Padding(
        padding: padding ?? const EdgeInsets.symmetric(vertical: 16),
        child: Row(
          children: [
            Expanded(
              child: Container(
                height: height,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [dividerColor.withOpacity(0.3), dividerColor],
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  gradient: AppTheme.foodGradient,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  text!,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    fontFamily: 'Inter',
                  ),
                ),
              ),
            ),
            Expanded(
              child: Container(
                height: height,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [dividerColor, dividerColor.withOpacity(0.3)],
                  ),
                ),
              ),
            ),
          ],
        ),
      );
    }
    
    return Padding(
      padding: padding ?? const EdgeInsets.symmetric(vertical: 8),
      child: Container(
        height: height,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              dividerColor.withOpacity(0.3),
              dividerColor,
              dividerColor.withOpacity(0.3),
            ],
          ),
        ),
      ),
    );
  }
}

class FoodIconDivider extends StatelessWidget {
  final IconData icon;
  final Color? iconColor;
  final Color? lineColor;
  final double lineHeight;
  final EdgeInsetsGeometry? padding;

  const FoodIconDivider({
    super.key,
    required this.icon,
    this.iconColor,
    this.lineColor,
    this.lineHeight = 1.0,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    final iconColorValue = iconColor ?? AppTheme.primaryOrange;
    final lineColorValue = lineColor ?? AppTheme.lightGray;
    
    return Padding(
      padding: padding ?? const EdgeInsets.symmetric(vertical: 16),
      child: Row(
        children: [
          Expanded(
            child: Container(
              height: lineHeight,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [lineColorValue.withOpacity(0.3), lineColorValue],
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: iconColorValue.withOpacity(0.1),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: iconColorValue.withOpacity(0.3)),
              ),
              child: Icon(
                icon,
                color: iconColorValue,
                size: 16,
              ),
            ),
          ),
          Expanded(
            child: Container(
              height: lineHeight,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [lineColorValue, lineColorValue.withOpacity(0.3)],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class FoodPatternDivider extends StatelessWidget {
  final double height;
  final Color? color;
  final EdgeInsetsGeometry? padding;

  const FoodPatternDivider({
    super.key,
    this.height = 2.0,
    this.color,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    final dividerColor = color ?? AppTheme.primaryOrange;
    
    return Padding(
      padding: padding ?? const EdgeInsets.symmetric(vertical: 16),
      child: CustomPaint(
        size: Size(double.infinity, height),
        painter: _FoodPatternPainter(dividerColor),
      ),
    );
  }
}

class _FoodPatternPainter extends CustomPainter {
  final Color color;

  _FoodPatternPainter(this.color);

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = 2.0
      ..style = PaintingStyle.stroke;

    final path = Path();
    final waveHeight = size.height * 0.5;
    final waveLength = size.width / 8;

    for (double x = 0; x <= size.width; x += waveLength) {
      final y = size.height / 2 + waveHeight * sin(x / waveLength);
      if (x == 0) {
        path.moveTo(x, y);
      } else {
        path.lineTo(x, y);
      }
    }

    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
