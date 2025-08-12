# Flutter Localization Integration Guide

This guide explains how to integrate the Laravel localization API with your Flutter application.

## API Endpoints

The Laravel backend provides the following API endpoints for localization:

- `GET /api/language/translations/{locale?}` - Get all translations for a specific locale (defaults to current locale if not specified)
- `POST /api/language/switch` - Switch the application locale (requires `locale` parameter)

## Flutter Implementation

### 1. Create a Language Service

Create a service to handle API calls for language translations:

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class LanguageService {
  final String baseUrl = 'https://your-api-url.com/api';
  
  // Store the current locale
  String _currentLocale = 'en';
  
  // Store translations
  Map<String, dynamic> _translations = {};
  
  // Getter for current locale
  String get currentLocale => _currentLocale;
  
  // Initialize the language service
  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    _currentLocale = prefs.getString('locale') ?? 'en';
    await fetchTranslations(_currentLocale);
  }
  
  // Fetch translations from API
  Future<void> fetchTranslations(String locale) async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/language/translations/$locale'),
        headers: {
          'Accept-Language': locale,
          'Content-Type': 'application/json',
        },
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        _translations = data['translations'];
        _currentLocale = data['locale'];
        
        // Save locale to preferences
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('locale', _currentLocale);
      }
    } catch (e) {
      print('Error fetching translations: $e');
    }
  }
  
  // Switch language
  Future<void> switchLanguage(String locale) async {
    if (locale == _currentLocale) return;
    
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/language/switch'),
        headers: {
          'Content-Type': 'application/json',
        },
        body: jsonEncode({'locale': locale}),
      );
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        _translations = data['translations'];
        _currentLocale = data['locale'];
        
        // Save locale to preferences
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('locale', _currentLocale);
      }
    } catch (e) {
      print('Error switching language: $e');
    }
  }
  
  // Get translation by key
  String translate(String key) {
    final keys = key.split('.');
    dynamic value = _translations;
    
    for (final k in keys) {
      if (value is Map && value.containsKey(k)) {
        value = value[k];
      } else {
        return key; // Return the key if translation not found
      }
    }
    
    return value.toString();
  }
}
```

### 2. Create a Language Provider

Create a provider to manage the language state:

```dart
import 'package:flutter/material.dart';
import 'language_service.dart';

class LanguageProvider extends ChangeNotifier {
  final LanguageService _languageService = LanguageService();
  
  LanguageProvider() {
    _init();
  }
  
  Future<void> _init() async {
    await _languageService.init();
    notifyListeners();
  }
  
  String get currentLocale => _languageService.currentLocale;
  
  String translate(String key) {
    return _languageService.translate(key);
  }
  
  Future<void> switchLanguage(String locale) async {
    await _languageService.switchLanguage(locale);
    notifyListeners();
  }
  
  // Check if the current locale is RTL
  bool get isRtl => currentLocale == 'ar';
}
```

### 3. Set Up Provider in Main App

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'language_provider.dart';

void main() {
  runApp(
    ChangeNotifierProvider(
      create: (_) => LanguageProvider(),
      child: MyApp(),
    ),
  );
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final languageProvider = Provider.of<LanguageProvider>(context);
    
    return MaterialApp(
      title: 'Multilingual App',
      // Set text direction based on language
      locale: Locale(languageProvider.currentLocale),
      // Support RTL layout
      builder: (context, child) {
        return Directionality(
          textDirection: languageProvider.isRtl ? TextDirection.rtl : TextDirection.ltr,
          child: child!,
        );
      },
      home: HomePage(),
    );
  }
}
```

### 4. Using Translations in Widgets

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'language_provider.dart';

class HomePage extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final lang = Provider.of<LanguageProvider>(context);
    
    return Scaffold(
      appBar: AppBar(
        title: Text(lang.translate('welcome')),
      ),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              lang.translate('dashboard'),
              style: TextStyle(fontSize: 24),
            ),
            SizedBox(height: 20),
            ElevatedButton(
              onPressed: () => lang.switchLanguage(lang.currentLocale == 'en' ? 'ar' : 'en'),
              child: Text(lang.translate('switch_language')),
            ),
          ],
        ),
      ),
    );
  }
}
```

## API Headers for Localization

When making API requests to your Laravel backend, always include the `Accept-Language` header with the current locale:

```dart
final response = await http.get(
  Uri.parse('$baseUrl/your-endpoint'),
  headers: {
    'Accept-Language': languageProvider.currentLocale,
    'Content-Type': 'application/json',
  },
);
```

This ensures that any localized responses from the API will be in the correct language.

## Testing the API

You can test the API endpoints using tools like Postman:

1. Get translations: `GET /api/language/translations/ar`
2. Switch language: `POST /api/language/switch` with body `{"locale": "ar"}`

## Additional Considerations

1. Handle loading states while fetching translations
2. Implement error handling for failed API requests
3. Consider caching translations locally to reduce API calls
4. Add a language selection screen for users to choose their preferred language 