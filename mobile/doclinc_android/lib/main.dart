import 'dart:async';

import 'package:file_selector/file_selector.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';

const String docLinkHost = 'doclinc.sectunnel.online';
final Uri docLinkStartUri = Uri.https(docLinkHost, '/');

enum NavigationDisposition { webView, external, blocked }

bool isTrustedDocLinkUri(Uri? uri) {
  return uri != null &&
      uri.scheme.toLowerCase() == 'https' &&
      uri.host.toLowerCase() == docLinkHost &&
      uri.userInfo.isEmpty;
}

NavigationDisposition navigationDispositionFor(Uri? uri) {
  if (isTrustedDocLinkUri(uri)) {
    return NavigationDisposition.webView;
  }
  if (uri == null) {
    return NavigationDisposition.blocked;
  }

  final String scheme = uri.scheme.toLowerCase();
  if (scheme == 'https' ||
      const <String>{
        'tel',
        'mailto',
        'sms',
        'geo',
        'intent',
        'whatsapp',
      }.contains(scheme)) {
    return NavigationDisposition.external;
  }
  return NavigationDisposition.blocked;
}

List<String> sanitizedAcceptedMimeTypes(Iterable<String> acceptedTypes) {
  final Set<String> result = <String>{};
  for (final String rawType in acceptedTypes) {
    final String type = rawType.trim().toLowerCase();
    if (type.isEmpty || type == '*/*') {
      continue;
    }
    if (type.length <= 100 &&
        RegExp(r'^[a-z0-9!#$&^_.+*-]+/[a-z0-9!#$&^_.+*-]+$').hasMatch(type)) {
      result.add(type);
    }
  }
  return result.toList(growable: false);
}

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const DocLinkApp());
}

class DocLinkApp extends StatelessWidget {
  const DocLinkApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'DocLink',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF176B5B)),
        useMaterial3: true,
      ),
      home: const DocLinkWebView(),
    );
  }
}

class DocLinkWebView extends StatefulWidget {
  const DocLinkWebView({super.key});

  @override
  State<DocLinkWebView> createState() => _DocLinkWebViewState();
}

class _DocLinkWebViewState extends State<DocLinkWebView> {
  late final WebViewController _controller;
  int _loadingProgress = 0;
  bool _mainFrameFailed = false;

  @override
  void initState() {
    super.initState();
    _controller =
        WebViewController(
            onPermissionRequest: (WebViewPermissionRequest request) {
              unawaited(_handleMediaPermissionRequest(request));
            },
          )
          ..setJavaScriptMode(JavaScriptMode.unrestricted)
          ..setBackgroundColor(Colors.white)
          ..setNavigationDelegate(
            NavigationDelegate(
              onNavigationRequest: _handleNavigationRequest,
              onPageStarted: (_) {
                if (!mounted) {
                  return;
                }
                setState(() {
                  _mainFrameFailed = false;
                  _loadingProgress = 0;
                });
              },
              onProgress: (int progress) {
                if (mounted) {
                  setState(() => _loadingProgress = progress.clamp(0, 100));
                }
              },
              onPageFinished: (_) {
                if (mounted) {
                  setState(() => _loadingProgress = 100);
                }
              },
              onWebResourceError: (WebResourceError error) {
                if (error.isForMainFrame == true && mounted) {
                  setState(() {
                    _mainFrameFailed = true;
                    _loadingProgress = 100;
                  });
                }
              },
            ),
          );

    unawaited(_configureAndroidWebView());
  }

  Future<void> _configureAndroidWebView() async {
    final platform = _controller.platform;
    if (platform is! AndroidWebViewController) {
      return;
    }

    await platform.setMediaPlaybackRequiresUserGesture(false);
    await platform.setGeolocationEnabled(true);
    await platform.setMixedContentMode(MixedContentMode.neverAllow);
    await platform.setOnShowFileSelector(_handleFileSelection);
    await platform.setGeolocationPermissionsPromptCallbacks(
      onShowPrompt: _handleGeolocationPrompt,
    );

    final WebViewCookieManager cookieManager = WebViewCookieManager();
    final cookiePlatform = cookieManager.platform;
    if (cookiePlatform is AndroidWebViewCookieManager) {
      await cookiePlatform.setAcceptThirdPartyCookies(platform, true);
    }

    await _controller.loadRequest(docLinkStartUri);
  }

  NavigationDecision _handleNavigationRequest(NavigationRequest request) {
    final Uri? uri = Uri.tryParse(request.url);
    switch (navigationDispositionFor(uri)) {
      case NavigationDisposition.webView:
        return NavigationDecision.navigate;
      case NavigationDisposition.external:
        if (uri != null) {
          unawaited(_launchExternally(uri));
        }
        return NavigationDecision.prevent;
      case NavigationDisposition.blocked:
        return NavigationDecision.prevent;
    }
  }

  Future<void> _launchExternally(Uri uri) async {
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } on Object {
      // Navigation stays blocked inside the WebView if no external app accepts it.
    }
  }

  Future<Uri?> _currentTrustedUri() async {
    final String? currentUrl = await _controller.currentUrl();
    final Uri? uri = currentUrl == null ? null : Uri.tryParse(currentUrl);
    return isTrustedDocLinkUri(uri) ? uri : null;
  }

  Future<void> _handleMediaPermissionRequest(
    WebViewPermissionRequest request,
  ) async {
    if (await _currentTrustedUri() == null || request.types.isEmpty) {
      await request.deny();
      return;
    }

    final List<Permission> permissions = <Permission>[];
    for (final WebViewPermissionResourceType type in request.types) {
      if (type == WebViewPermissionResourceType.camera) {
        permissions.add(Permission.camera);
      } else if (type == WebViewPermissionResourceType.microphone) {
        permissions.add(Permission.microphone);
      } else {
        await request.deny();
        return;
      }
    }

    final List<Permission> uniquePermissions = permissions.toSet().toList();
    final Map<Permission, PermissionStatus> statuses = await uniquePermissions
        .request();
    final bool granted =
        statuses.length == uniquePermissions.length &&
        statuses.values.every((PermissionStatus status) => status.isGranted);
    if (granted && await _currentTrustedUri() != null) {
      await request.grant();
    } else {
      await request.deny();
    }
  }

  Future<GeolocationPermissionsResponse> _handleGeolocationPrompt(
    GeolocationPermissionsRequestParams request,
  ) async {
    final Uri? requestedOrigin = Uri.tryParse(request.origin);
    if (!isTrustedDocLinkUri(requestedOrigin) ||
        await _currentTrustedUri() == null) {
      return const GeolocationPermissionsResponse(allow: false, retain: false);
    }

    final Map<Permission, PermissionStatus> statuses = await <Permission>[
      Permission.locationWhenInUse,
    ].request();
    final bool allowed =
        statuses[Permission.locationWhenInUse]?.isGranted == true &&
        await _currentTrustedUri() != null;
    return GeolocationPermissionsResponse(allow: allowed, retain: false);
  }

  Future<List<String>> _handleFileSelection(FileSelectorParams params) async {
    if (await _currentTrustedUri() == null) {
      return <String>[];
    }

    final List<String> mimeTypes = sanitizedAcceptedMimeTypes(
      params.acceptTypes,
    );
    final List<XTypeGroup> typeGroups = mimeTypes.isEmpty
        ? const <XTypeGroup>[]
        : <XTypeGroup>[XTypeGroup(label: 'File', mimeTypes: mimeTypes)];

    try {
      if (params.mode == FileSelectorMode.openMultiple) {
        final List<XFile> files = await openFiles(
          acceptedTypeGroups: typeGroups,
        );
        if (await _currentTrustedUri() == null) {
          return <String>[];
        }
        return files.map((XFile file) => file.path).toList(growable: false);
      }
      if (params.mode == FileSelectorMode.open) {
        final XFile? file = await openFile(acceptedTypeGroups: typeGroups);
        if (file == null || await _currentTrustedUri() == null) {
          return <String>[];
        }
        return <String>[file.path];
      }
    } on Object {
      return <String>[];
    }
    return <String>[];
  }

  Future<void> _retry() async {
    if (mounted) {
      setState(() {
        _mainFrameFailed = false;
        _loadingProgress = 0;
      });
    }
    final Uri? current = await _currentTrustedUri();
    if (current == null) {
      await _controller.loadRequest(docLinkStartUri);
    } else {
      await _controller.reload();
    }
  }

  Future<void> _handleBack() async {
    if (await _controller.canGoBack()) {
      await _controller.goBack();
      return;
    }
    await SystemNavigator.pop();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope<Object?>(
      canPop: false,
      onPopInvokedWithResult: (bool didPop, Object? result) {
        if (!didPop) {
          unawaited(_handleBack());
        }
      },
      child: Scaffold(
        body: SafeArea(
          child: Stack(
            children: <Widget>[
              WebViewWidget(controller: _controller),
              if (_loadingProgress < 100 && !_mainFrameFailed)
                Align(
                  alignment: Alignment.topCenter,
                  child: LinearProgressIndicator(value: _loadingProgress / 100),
                ),
              if (_mainFrameFailed)
                ColoredBox(
                  color: Theme.of(context).colorScheme.surface,
                  child: Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: <Widget>[
                          const Icon(Icons.cloud_off_outlined, size: 48),
                          const SizedBox(height: 16),
                          Text(
                            'DocLink tidak dapat dimuat.',
                            style: Theme.of(context).textTheme.titleMedium,
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 12),
                          FilledButton(
                            onPressed: _retry,
                            child: const Text('Coba Lagi'),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
