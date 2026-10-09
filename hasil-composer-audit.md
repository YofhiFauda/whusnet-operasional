Unknown option "--class". Most similar options are --all, --uses, --colors, --columns, --covers
root@7721bdbc268e:/var/www# composer audit
The repository at "/var/www" does not have the correct ownership and git refuses to use it:

fatal: detected dubious ownership in repository at '/var/www'
To add an exception for this directory, call:

git config --global --add safe.directory /var/www

Found 25 security vulnerability advisories affecting 5 packages:
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/guzzle                                                                |
| Severity          | high                                                                             |
| Advisory ID       | PKSA-gcrk-3vtt-1r14                                                              |
| CVE               | CVE-2026-69246                                                                   |
| Title             | Guzzle: Noncanonical host can bypass host-based checks                           |
| URL               | https://github.com/advisories/GHSA-v5mv-p594-2x33                                |
| Affected versions | >=8.0.0,<8.0.1|<7.15.2                                                           |
| Reported at       | 2026-08-03T21:07:26+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/guzzle                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-cnw1-2ytm-cgr8                                                              |
| CVE               | CVE-2026-69245                                                                   |
| Title             | Guzzle: Noncanonical cookie domain keeps subdomain scope                         |
| URL               | https://github.com/advisories/GHSA-f7vp-7xgx-4w4r                                |
| Affected versions | >=8.0.0,<8.0.1|<7.15.2                                                           |
| Reported at       | 2026-08-03T21:05:26+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/guzzle                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-fy2t-3c5f-827y                                                              |
| CVE               | CVE-2026-67354                                                                   |
| Title             | Guzzle: URI fragments disclosed in redirect Referer headers                      |
| URL               | https://github.com/advisories/GHSA-h95v-h523-3mw8                                |
| Affected versions | <7.15.1                                                                          |
| Reported at       | 2026-07-20T23:28:36+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/guzzle                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-qxvb-2bpp-dnk6                                                              |
| CVE               | CVE-2026-67355                                                                   |
| Title             | Guzzle: Host-only cookie scope is not preserved                                  |
| URL               | https://github.com/advisories/GHSA-wm3w-8rrp-j577                                |
| Affected versions | <7.15.1                                                                          |
| Reported at       | 2026-07-20T23:27:49+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/guzzle                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-bbs6-q5q9-f3t4                                                              |
| CVE               | CVE-2026-67353                                                                   |
| Title             | Guzzle: Unbounded response cookies risk denial of service                        |
| URL               | https://github.com/advisories/GHSA-f283-ghqc-fg79                                |
| Affected versions | <7.15.1                                                                          |
| Reported at       | 2026-07-20T23:27:02+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/guzzle                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-bcdd-5xc7-gwfb                                                              |
| CVE               | CVE-2026-59883                                                                   |
| Title             | Guzzle: Cookie Disclosure and Injection via IP-Address Domains                   |
| URL               | https://github.com/advisories/GHSA-g446-98w2-8p5w                                |
| Affected versions | <7.12.3                                                                          |
| Reported at       | 2026-07-20T22:00:09+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/guzzle                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-pwsk-hy21-4gby                                                              |
| CVE               | CVE-2026-67339                                                                   |
| Title             | Guzzle: Proxy-Authorization headers can be sent to origin servers                |
| URL               | https://github.com/advisories/GHSA-94pj-82f3-465w                                |
| Affected versions | <7.14.2                                                                          |
| Reported at       | 2026-07-20T21:46:02+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/guzzle                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-93qv-9n9h-6k6p                                                              |
| CVE               | CVE-2026-55767                                                                   |
| Title             | Dot-only cookie domains match all hosts                                          |
| URL               | https://github.com/guzzle/guzzle/security/advisories/GHSA-cwxw-98qj-8qjx         |
| Affected versions | <7.12.1                                                                          |
| Reported at       | 2026-06-18T14:12:49+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/guzzle                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-k22t-f949-t9g6                                                              |
| CVE               | CVE-2026-55568                                                                   |
| Title             | Silent HTTPS proxy downgrade to cleartext                                        |
| URL               | https://github.com/guzzle/guzzle/security/advisories/GHSA-wpwq-4j6v-78m3         |
| Affected versions | <7.12.1                                                                          |
| Reported at       | 2026-06-18T14:12:49+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/psr7                                                                  |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-vznr-tgp9-fd7d                                                              |
| CVE               | CVE-2026-59882                                                                   |
| Title             | guzzlehttp/psr7: Host Confusion via Weak URI Host Validation                     |
| URL               | https://github.com/advisories/GHSA-c2w2-prh8-qm98                                |
| Affected versions | <2.12.3                                                                          |
| Reported at       | 2026-07-21T18:35:25+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | guzzlehttp/psr7                                                                  |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-7qs6-zvnz-h66r                                                              |
| CVE               | CVE-2026-55766                                                                   |
| Title             | CRLF injection in HTTP start-line serialization                                  |
| URL               | https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432           |
| Affected versions | <2.12.1                                                                          |
| Reported at       | 2026-06-18T09:49:37+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | laravel/framework                                                                |
| Severity          | low                                                                              |
| Advisory ID       | PKSA-d5tc-s1qs-h781                                                              |
| CVE               | CVE-2026-102279                                                                  |
| Title             | Laravel: XSS in Debug Page Information                                           |
| URL               | https://github.com/advisories/GHSA-jh5r-qr3c-85q8                                |
| Affected versions | >=13.0.0,<13.30.0|<12.69.0                                                       |
| Reported at       | 2026-09-29T18:24:25+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-m2dq-1fhr-29b1                                                              |
| CVE               | NO CVE                                                                           |
| Title             | league/commonmark: DisallowedRawHtml bypassed when a disallowed tag name ends    |
|                   | the raw-HTML literal                                                             |
| URL               | https://github.com/advisories/GHSA-97jj-33gv-5xf9                                |
| Affected versions | >=1.3.0,<=2.10.1                                                                 |
| Reported at       | 2026-09-30T15:36:33+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | high                                                                             |
| Advisory ID       | PKSA-m4t9-vsgq-8khn                                                              |
| CVE               | NO CVE                                                                           |
| Title             | league/commonmark: Quadratic-time denial of service in the GitHub Flavored       |
|                   | Markdown Table extension block-start scan                                        |
| URL               | https://github.com/advisories/GHSA-3q6v-r5mr-hxv8                                |
| Affected versions | >=2.0.0,<=2.10.1                                                                 |
| Reported at       | 2026-09-30T15:36:13+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | high                                                                             |
| Advisory ID       | PKSA-zyf5-hrxv-hrd7                                                              |
| CVE               | NO CVE                                                                           |
| Title             | league/commonmark: Denial of service via distinctly-named attributes in the      |
|                   | Attributes extension                                                             |
| URL               | https://github.com/advisories/GHSA-8rr7-cvq3-gmfh                                |
| Affected versions | >=1.5.0,<2.10.0                                                                  |
| Reported at       | 2026-09-01T20:28:40+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | high                                                                             |
| Advisory ID       | PKSA-nv44-1b4d-6gjg                                                              |
| CVE               | NO CVE                                                                           |
| Title             | league/commonmark: Denial of service in the SmartPunct and Attributes extensions |
| URL               | https://github.com/advisories/GHSA-jjv6-8j6v-6j52                                |
| Affected versions | >=1.5.0,<2.9.1                                                                   |
| Reported at       | 2026-09-01T20:21:45+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | high                                                                             |
| Advisory ID       | PKSA-kr3s-894t-g5w2                                                              |
| CVE               | NO CVE                                                                           |
| Title             | league/commonmark XSS: `on*` event-handler filter in `AttributesExtension`       |
|                   | bypassed with a U+000C form feed                                                 |
| URL               | https://github.com/advisories/GHSA-f8fg-pg57-v4j8                                |
| Affected versions | >=2.7.0,<2.9.1                                                                   |
| Reported at       | 2026-09-01T20:18:29+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | high                                                                             |
| Advisory ID       | PKSA-9q1p-3s19-bp1q                                                              |
| CVE               | NO CVE                                                                           |
| Title             | league/commonmark: Denial of service via crafted code fences, reference links,   |
|                   | and emphasis delimiters                                                          |
| URL               | https://github.com/advisories/GHSA-j8pm-gj4c-rq4x                                |
| Affected versions | >=0.6.0,<2.9.1                                                                   |
| Reported at       | 2026-09-01T20:17:59+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-5mzr-szzf-z6cn                                                              |
| CVE               | NO CVE                                                                           |
| Title             | league/commonmark: Denial of service via deeply nested XML output                |
| URL               | https://github.com/advisories/GHSA-mj63-m3rc-8ppr                                |
| Affected versions | >=2.0.0,<2.9.0                                                                   |
| Reported at       | 2026-08-06T20:42:54+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | high                                                                             |
| Advisory ID       | PKSA-cqd6-fg4n-nxpf                                                              |
| CVE               | NO CVE                                                                           |
| Title             | league/commonmark: Denial of service via colliding heading slugs                 |
| URL               | https://github.com/advisories/GHSA-mh25-x5hq-wrqp                                |
| Affected versions | >=2.0.0,<2.9.0                                                                   |
| Reported at       | 2026-08-06T20:41:45+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | high                                                                             |
| Advisory ID       | PKSA-1q6p-sqkj-8mmj                                                              |
| CVE               | NO CVE                                                                           |
| Title             | league/commonmark:  Denial of service via duplicate footnote definitions         |
| URL               | https://github.com/advisories/GHSA-jfm3-95jq-q3rf                                |
| Affected versions | >=1.5.0,<2.9.0                                                                   |
| Reported at       | 2026-08-06T20:40:53+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | high                                                                             |
| Advisory ID       | PKSA-mc58-w91n-f5gv                                                              |
| CVE               | NO CVE                                                                           |
| Title             | league/commonmark: Denial of service via adjacent inline attribute blocks        |
| URL               | https://github.com/advisories/GHSA-g2gp-3wwq-f4ph                                |
| Affected versions | >=1.5.0,<2.9.0                                                                   |
| Reported at       | 2026-08-06T20:39:52+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | high                                                                             |
| Advisory ID       | PKSA-t21r-vtr5-3mdz                                                              |
| CVE               | CVE-2026-71488                                                                   |
| Title             | league/commonmark: Quadratic-time denial of service when parsing crafted         |
|                   | Markdown                                                                         |
| URL               | https://github.com/advisories/GHSA-2q4p-g7hv-5rgv                                |
| Affected versions | >=0.6.0,<2.9.0                                                                   |
| Reported at       | 2026-08-06T20:37:20+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/commonmark                                                                |
| Severity          | medium                                                                           |
| Advisory ID       | PKSA-scnn-p8mm-jbft                                                              |
| CVE               | CVE-2026-71478                                                                   |
| Title             | league/commonmark: AttributesExtension href/src unsafe-link filter bypass via    |
|                   | embedded control bytes                                                           |
| URL               | https://github.com/advisories/GHSA-29pj-957v-52mc                                |
| Affected versions | >=1.5.0,<=2.8.3                                                                  |
| Reported at       | 2026-08-06T20:30:39+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+
+-------------------+----------------------------------------------------------------------------------+
| Package           | league/flysystem                                                                 |
| Severity          | low                                                                              |
| Advisory ID       | PKSA-w9tt-7782-78jx                                                              |
| CVE               | CVE-2026-102601                                                                  |
| Title             | Flysystem: WhitespacePathNormalizer's control-character (CorruptedPathDetected)  |
|                   | check is bypassed by malformed UTF-8 in the path, affecting every adapter        |
| URL               | https://github.com/advisories/GHSA-cxf4-7mrp-vvpr                                |
| Affected versions | <=3.35.2                                                                         |
| Reported at       | 2026-09-29T18:10:14+00:00                                                        |
+-------------------+----------------------------------------------------------------------------------+