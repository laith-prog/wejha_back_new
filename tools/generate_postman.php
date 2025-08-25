<?php
// tools/generate_postman.php
// Generate a Postman collection by scanning Laravel routes and extracting validation rules

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Bootstrap Laravel
$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';
$app = require $basePath . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Helpers
function readFileSegment(string $file, int $startLine, ?int $endLine = null): string {
    $lines = file($file);
    $total = count($lines);
    $start = max(1, $startLine) - 1;
    $end = ($endLine === null ? $total : min($endLine, $total)) - 1;
    $buf = '';
    for ($i = $start; $i <= $end; $i++) { $buf .= $lines[$i]; }
    return $buf;
}

function stripPhpComments(string $code): string {
    // Remove /* ... */ comments
    $code = preg_replace('#/\*.*?\*/#s', '', $code);
    // Remove // ... comments
    $code = preg_replace('#//.*?$#m', '', $code);
    return $code;
}

function extractValidationArrays(string $code): array {
    $results = [];
    // Strip comments first
    $code = stripPhpComments($code);
    // Normalize whitespace
    $codeNorm = preg_replace('/\s+/', ' ', $code);

    // Patterns to locate Validator::make(..., [ ... ]) and $request->validate([ ... ])
    $candidates = [];

    // Find Validator::make with array rules as second argument
    $pattern1 = '/Validator::make\s*\(\s*\$request->all\s*\(\s*\)\s*,\s*(\[)/i';
    if (preg_match_all($pattern1, $codeNorm, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as $match) { $candidates[] = $match[1]; }
    }

    // Find $request->validate([ ... ])
    $pattern2 = '/\$request->validate\s*\(\s*(\[)/i';
    if (preg_match_all($pattern2, $codeNorm, $m2, PREG_OFFSET_CAPTURE)) {
        foreach ($m2[1] as $match) { $candidates[] = $match[1]; }
    }

    sort($candidates);

    foreach ($candidates as $pos) {
        $arr = extractPhpArrayFrom($codeNorm, $pos);
        if ($arr !== null) {
            $rules = parsePhpArrayToAssoc($arr);
            if (!empty($rules)) { $results[] = $rules; }
        }
    }

    return $results;
}

function extractPhpArrayFrom(string $code, int $startBracketPos): ?string {
    // Given a position at '[' in normalized code, extract the array text until the matching ']'
    $depth = 0; $out = '';
    for ($i = $startBracketPos; $i < strlen($code); $i++) {
        $ch = $code[$i];
        $out .= $ch;
        if ($ch === '[') { $depth++; }
        elseif ($ch === ']') { $depth--; if ($depth === 0) { break; } }
    }
    if ($depth !== 0) return null;
    return $out;
}

function parsePhpArrayToAssoc(string $arrText): array {
    // Very light parser for arrays like ['email' => 'required|email', 'password' => ['required','string']]
    // Not a full PHP parser; handles common validation arrays
    $arrText = trim($arrText);
    if ($arrText === '' || $arrText[0] !== '[' || substr($arrText, -1) !== ']') return [];
    $inner = substr($arrText, 1, -1);

    $items = [];
    $buf = '';
    $depth = 0;
    $inStr = false; $strDelim = '';

    $pairs = [];
    for ($i = 0; $i < strlen($inner); $i++) {
        $ch = $inner[$i];
        $buf .= $ch;
        if ($inStr) {
            if ($ch === $strDelim && ($i === 0 || $inner[$i-1] !== '\\')) { $inStr = false; }
            continue;
        } else {
            if ($ch === '"' || $ch === '\'') { $inStr = true; $strDelim = $ch; continue; }
            if ($ch === '[') { $depth++; continue; }
            if ($ch === ']') { $depth--; continue; }
            if ($ch === ',' && $depth === 0) { $pairs[] = trim($buf, ", "); $buf = ''; }
        }
    }
    if (trim($buf) !== '') { $pairs[] = trim($buf, ", "); }

    foreach ($pairs as $pair) {
        $parts = preg_split('/=>/', $pair, 2);
        if (count($parts) !== 2) continue;
        $k = trim($parts[0]);
        $v = trim($parts[1]);
        $k = trim($k, "'\"");
        $items[$k] = normalizeRuleValue($v);
    }

    return $items;
}

function normalizeRuleValue(string $v) {
    $v = trim($v);
    // If array [...]
    if ($v !== '' && $v[0] === '[') {
        // split top-level commas
        $inner = substr($v, 1, -1);
        $tokens = [];
        $buf = '';
        $depth = 0; $inStr = false; $strDelim = '';
        for ($i = 0; $i < strlen($inner); $i++) {
            $ch = $inner[$i];
            $buf .= $ch;
            if ($inStr) {
                if ($ch === $strDelim && ($i === 0 || $inner[$i-1] !== '\\')) { $inStr = false; }
            } else {
                if ($ch === '"' || $ch === '\'') { $inStr = true; $strDelim = $ch; }
                elseif ($ch === '[') { $depth++; }
                elseif ($ch === ']') { $depth--; }
                elseif ($ch === ',' && $depth === 0) { $tokens[] = trim($buf, ", "); $buf=''; }
            }
        }
        if (trim($buf) !== '') $tokens[] = trim($buf, ", ");
        return array_map(function($t){ return trim($t, "'\""); }, $tokens);
    }
    // If string 'required|email'
    return array_filter(explode('|', trim($v, "'\"")));
}

// Extract query params that are explicitly accessed from Request
function extractQueryParams(string $code): array {
    $code = stripPhpComments($code);
    $params = [];
    // $request->input('name') / query('name') / get('name')
    if (preg_match_all('/\$request->(?:input|query|get)\s*\(\s*[\'\"]([a-zA-Z_][\w-]*)[\'\"]/i', $code, $m)) {
        foreach ($m[1] as $p) { $params[$p] = true; }
    }
    // $request->has('name')
    if (preg_match_all('/\$request->has\s*\(\s*[\'\"]([a-zA-Z_][\w-]*)[\'\"]/i', $code, $m2)) {
        foreach ($m2[1] as $p) { $params[$p] = true; }
    }
    return array_values(array_unique(array_keys($params)));
}

// Detect array-like query params (e.g., is_array($request->amenities))
function extractArrayQueryParams(string $code): array {
    $code = stripPhpComments($code);
    $arrayParams = [];
    if (preg_match_all('/is_array\s*\(\s*\$request->([a-zA-Z_][\w]*)\s*\)/i', $code, $m)) {
        foreach ($m[1] as $p) { $arrayParams[$p] = true; }
    }
    return array_values(array_unique(array_keys($arrayParams)));
}

function exampleForQuery(string $key): string {
    $k = strtolower($key);
    if (in_array($k, ['page','per_page','limit','offset'])) return '1';
    if (str_starts_with($k, 'min_') || str_starts_with($k, 'max_')) return '1';
    if (in_array($k, ['lat','latitude'])) return '24.7136';
    if (in_array($k, ['lng','longitude'])) return '46.6753';
    if ($k === 'radius') return '10';
    if (preg_match('/(^|_)id$/', $k)) return '1';
    if (in_array($k, ['sort_by'])) return 'created_at';
    if (in_array($k, ['sort_direction','order'])) return 'desc';
    if (in_array($k, ['furnished','under_construction','validate','is_mobile'])) return 'true';
    if (in_array($k, ['bedrooms','bathrooms','year','mileage','price'])) return '1';
    if (in_array($k, ['city','area','make','model','company_name','education_level','experience_level','vehicle_type','service_type','job_type','employment_type','purpose','property_type','gender','keyword'])) return 'string';
    return 'string';
}

function buildQueryEntries(array $queryParams, array $arrayParams): array {
    $entries = [];
    foreach ($queryParams as $p) {
        if (in_array($p, $arrayParams, true)) {
            // Provide two example items for array params using bracket notation
            $entries[] = ['key' => $p . '[]', 'value' => 'option1'];
            $entries[] = ['key' => $p . '[]', 'value' => 'option2'];
        } else {
            $entries[] = ['key' => $p, 'value' => exampleForQuery($p)];
        }
    }
    return $entries;
}

// Extract body keys from array assignments that read from $request, for arrays used in DB insert/update
function extractDbBodyKeys(string $code): array {
    $codeNoComments = stripPhpComments($code);
    $keys = [];
    // Find variables passed into DB::table(..)->insert/insertGetId/update($var)
    if (preg_match_all('/DB::table\(\s*[\'\"]([a-zA-Z_][\w]*)[\'\"]\s*\)->\s*(?:insertGetId|insert|update)\s*\(\s*\$([a-zA-Z_][\w]*)/i', $codeNoComments, $m)) {
        $vars = array_unique($m[2]);
        foreach ($vars as $var) {
            // Find $var = [ ... ]; (last assignment wins)
            if (preg_match_all('/\$' . preg_quote($var, '/') . '\s*=\s*\[(.*?)\];/s', $codeNoComments, $assigns)) {
                $arrInner = end($assigns[1]);
                // key => $request->prop
                if (preg_match_all('/[\'\"]([a-zA-Z_][\w]*)[\'\"]\s*=>\s*\$request->([a-zA-Z_][\w]*)/i', $arrInner, $m1)) {
                    foreach ($m1[1] as $k) { $keys[$k] = true; }
                }
                // key => $request->file('name')
                if (preg_match_all('/[\'\"]([a-zA-Z_][\w]*)[\'\"]\s*=>\s*\$request->file\(\s*[\'\"]([a-zA-Z_][\w]*)[\'\"]\s*\)/i', $arrInner, $m2)) {
                    foreach ($m2[1] as $k) { $keys[$k] = true; }
                }
            }
        }
    }
    return array_values(array_unique(array_keys($keys)));
}

function exampleForRules(array $rules, string $key): mixed {
    $typesLower = array_map('strtolower', $rules);
    if (in_array('email', $typesLower)) return 'user@example.com';
    if (in_array('date', $typesLower)) return '2024-01-01';
    if (in_array('boolean', $typesLower)) return true;
    if (preg_grep('/integer|numeric/', $typesLower)) return 1;
    if (preg_grep('/image|mimes|file/', $typesLower)) return '';
    foreach ($rules as $r) {
        if (preg_match('/min:(\d+)/i', $r, $mm)) { return str_repeat('a', max(1, (int)$mm[1])); }
    }
    if (str_contains($key, 'password')) return 'password123';
    if (str_contains($key, 'phone')) return '+1234567890';
    if (str_contains($key, 'name')) return 'John';
    return 'string';
}

function buildBodyFromKeys(array $keys, array $rulesMap): array {
    $body = [];
    foreach ($keys as $key) {
        $ruleArr = $rulesMap[$key] ?? [];
        $body[$key] = !empty($ruleArr) ? exampleForRules($ruleArr, $key) : 'string';
        // handle confirmed
        foreach ($ruleArr as $r) {
            if (stripos($r, 'confirmed') !== false) {
                $body[$key . '_confirmation'] = $body[$key];
            }
        }
    }
    return $body;
}

function makeItem(string $name, string $method, string $path, array $headers, array $queryParams, ?array $bodyData, array $arrayQueryParams): array {
    $url = [
        'raw' => '{{base_url}}/' . ltrim($path, '/'),
        'host' => ['{{base_url}}'],
        'path' => explode('/', trim($path, '/')),
    ];
    if (!empty($queryParams)) {
        $url['query'] = buildQueryEntries($queryParams, $arrayQueryParams);
    }

    $item = [
        'name' => $name,
        'request' => [
            'method' => $method,
            'header' => $headers,
            'url' => $url,
        ],
    ];

    if (in_array($method, ['POST','PUT','PATCH'])) {
        $item['request']['header'][] = ['key'=>'Content-Type','value'=>'application/json'];
        $item['request']['body'] = [
            'mode' => 'raw',
            'raw' => json_encode($bodyData ?: new stdClass(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)
        ];
    }

    return $item;
}

$routes = Route::getRoutes();

$collection = [
    'info' => [
        'name' => 'Wejha API Collection (Generated)',
        'description' => 'Generated from routes and controller validation rules (DB-matched)',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
    ],
    'variable' => [
        ['key'=>'base_url','value'=>'http://localhost:8000/api','type'=>'string'],
        ['key'=>'jwt_token','value'=>'','type'=>'string'],
    ],
    'item' => []
];

// Group items by first path segment
$groups = [];

foreach ($routes as $route) {
    $uri = $route->uri();
    if (!str_starts_with($uri, 'api')) continue; // keep only API routes

    $path = $uri;
    // base_url already ends with /api
    if (str_starts_with($uri, 'api/')) { $path = substr($uri, 4); }

    $methods = array_values(array_diff($route->methods(), ['HEAD']));

    $action = $route->getActionName();
    $controllerClass = null; $controllerMethod = null; $file = null; $methodStart = null; $methodEnd = null; $methodCode = '';

    if ($action && $action !== 'Closure') {
        if (str_contains($action, '@')) {
            [$controllerClass, $controllerMethod] = explode('@', $action);
            try {
                $ref = new ReflectionMethod($controllerClass, $controllerMethod);
                $file = $ref->getFileName();
                $methodStart = $ref->getStartLine();
                $methodEnd = $ref->getEndLine();
                $methodCode = readFileSegment($file, $methodStart, $methodEnd);
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    $rulesList = [];
    $rulesMap = [];
    $queryParams = [];
    $dbBodyKeys = [];
    $arrayQueryParams = [];

    if ($methodCode) {
        $rulesList = extractValidationArrays($methodCode);
        // Merge all rules blocks into a single map
        foreach ($rulesList as $block) {
            foreach ($block as $k => $v) { $rulesMap[$k] = is_array($v) ? $v : (array)$v; }
        }
        $queryParams = extractQueryParams($methodCode);
        $arrayQueryParams = extractArrayQueryParams($methodCode);
        $dbBodyKeys = extractDbBodyKeys($methodCode);
    }

    // Build headers (auth)
    $headers = [];
    $mw = $route->gatherMiddleware();
    $mwStr = implode(',', $mw);
    if (preg_match('/auth:api|jwt\.auth|auth:sanctum/i', $mwStr)) {
        $headers[] = ['key' => 'Authorization', 'value' => 'Bearer {{jwt_token}}'];
    }

    $firstSeg = explode('/', trim($path, '/'))[0] ?? 'root';
    if (!isset($groups[$firstSeg])) { $groups[$firstSeg] = ['name'=>$firstSeg, 'item'=>[]]; }

    foreach ($methods as $method) {
        $name = ($controllerMethod ?: $path) . ' [' . $method . ']';

        // Decide body data only for methods that carry payload
        $bodyData = null;
        if (in_array($method, ['POST','PUT','PATCH'])) {
            // Combine validated keys and DB-bound keys
            $bodyKeys = array_values(array_unique(array_merge(array_keys($rulesMap), $dbBodyKeys)));
            if (!empty($bodyKeys)) {
                $bodyData = buildBodyFromKeys($bodyKeys, $rulesMap);
            }
        }

        $item = makeItem($name, $method, $path, $headers, $queryParams, $bodyData, $arrayQueryParams);
        $groups[$firstSeg]['item'][] = $item;
    }
}

// Finalize collection
$collection['item'] = array_values($groups);

// Write file
$outFile = $basePath . '/api-collection.generated.json';
file_put_contents($outFile, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Generated: $outFile\n";