import 'package:doclink/main.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('DocLink navigation policy', () {
    test('allows only the exact trusted HTTPS host in WebView', () {
      expect(
        navigationDispositionFor(
          Uri.parse('https://doclinc.sectunnel.online/path'),
        ),
        NavigationDisposition.webView,
      );
      expect(
        navigationDispositionFor(
          Uri.parse('http://doclinc.sectunnel.online/path'),
        ),
        NavigationDisposition.blocked,
      );
      expect(
        navigationDispositionFor(
          Uri.parse('https://sub.doclinc.sectunnel.online/path'),
        ),
        NavigationDisposition.external,
      );
      expect(
        navigationDispositionFor(
          Uri.parse('https://doclinc.sectunnel.online@example.com/path'),
        ),
        NavigationDisposition.external,
      );
    });

    test('routes supported external schemes outside the WebView', () {
      for (final String url in <String>[
        'tel:+6200000000',
        'mailto:test@example.invalid',
        'sms:+6200000000',
        'geo:0,0',
        'intent://example',
        'whatsapp://send',
        'https://example.com',
      ]) {
        expect(
          navigationDispositionFor(Uri.parse(url)),
          NavigationDisposition.external,
        );
      }
    });

    test('blocks unknown and insecure schemes', () {
      expect(
        navigationDispositionFor(Uri.parse('ftp://example.com/file')),
        NavigationDisposition.blocked,
      );
      expect(
        navigationDispositionFor(Uri.parse('javascript:alert(1)')),
        NavigationDisposition.blocked,
      );
      expect(navigationDispositionFor(null), NavigationDisposition.blocked);
    });
  });

  test('sanitizes accepted MIME types deterministically', () {
    expect(
      sanitizedAcceptedMimeTypes(<String>[
        ' image/jpeg ',
        'IMAGE/JPEG',
        '*/*',
        'text/plain',
        'not-a-mime',
      ]),
      <String>['image/jpeg', 'text/plain'],
    );
  });
}
