<?php
declare(strict_types=1);
namespace App\Service;

class PoToMoCompiler
{
    /**
     * Compile a .po file to a .mo file.
     * Returns true on success.
     */
    public static function compile(string $poFile, string $moFile): bool
    {
        $content = file_get_contents($poFile);
        if ($content === false) {
            return false;
        }
        $entries = self::parsePo($content);
        $moData  = self::buildMo($entries);
        $dir = dirname($moFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($moFile, $moData) !== false;
    }

    /**
     * Parse a .po file into [msgid => msgstr] pairs.
     * @return array<string, string>
     */
    public static function parsePo(string $content): array
    {
        $entries = [];
        $msgid = null;
        $msgstr = null;
        $inMsgid = false;
        $inMsgstr = false;

        foreach (explode("\n", $content) as $line) {
            $line = rtrim($line);
            if (str_starts_with($line, '#') || $line === '') {
                if ($msgid !== null && $msgstr !== null && $msgid !== '') {
                    $entries[$msgid] = $msgstr;
                }
                if ($line === '' || str_starts_with($line, '#')) {
                    if ($line === '' && $msgid !== null) {
                        // @phpstan-ignore notIdentical.alwaysTrue
                        if ($msgid !== null && $msgstr !== null && $msgid !== '') {
                            $entries[$msgid] = $msgstr;
                        }
                        $msgid = null;
                        $msgstr = null;
                    }
                }
                $inMsgid = false;
                $inMsgstr = false;
                continue;
            }
            if (str_starts_with($line, 'msgid ')) {
                if ($msgid !== null && $msgstr !== null && $msgid !== '') {
                    $entries[$msgid] = $msgstr;
                }
                $msgid = self::unquote(substr($line, 6));
                $msgstr = null;
                $inMsgid = true;
                $inMsgstr = false;
                continue;
            }
            if (str_starts_with($line, 'msgstr ')) {
                $msgstr = self::unquote(substr($line, 7));
                $inMsgid = false;
                $inMsgstr = true;
                continue;
            }
            if (str_starts_with($line, '"')) {
                if ($inMsgid) {
                    $msgid .= self::unquote($line);
                } elseif ($inMsgstr) {
                    $msgstr .= self::unquote($line);
                }
            }
        }
        if ($msgid !== null && $msgstr !== null && $msgid !== '') {
            $entries[$msgid] = $msgstr;
        }
        return $entries;
    }

    /** @param array<string, string> $entries */
    private static function buildMo(array $entries): string
    {
        // Add header entry
        ksort($entries);
        $strings = ['' => ''];  // header
        foreach ($entries as $id => $str) {
            if ($id === '') continue;
            $strings[$id] = $str !== '' ? $str : $id;
        }

        $n = count($strings);
        $origTable  = [];
        $transTable = [];
        $strData    = '';
        $headerOffset = 28 + $n * 16;

        foreach ($strings as $id => $str) {
            $origTable[]  = [strlen($id),  $headerOffset + strlen($strData)];
            $strData .= $id . "\0";
            $transTable[] = [strlen($str), $headerOffset + strlen($strData)];
            $strData .= $str . "\0";
        }

        $mo  = pack('V', 0x950412de);  // magic
        $mo .= pack('V', 0);           // revision
        $mo .= pack('V', $n);          // number of strings
        $mo .= pack('V', 28);          // offset of original strings table
        $mo .= pack('V', 28 + $n * 8); // offset of translated strings table
        $mo .= pack('V', 0);           // hash table size
        $mo .= pack('V', 28 + $n * 16);// hash table offset

        foreach ($origTable as [$len, $off]) {
            $mo .= pack('V', $len) . pack('V', $off);
        }
        foreach ($transTable as [$len, $off]) {
            $mo .= pack('V', $len) . pack('V', $off);
        }
        $mo .= $strData;

        return $mo;
    }

    private static function unquote(string $s): string
    {
        $s = trim($s);
        if (str_starts_with($s, '"') && str_ends_with($s, '"')) {
            $s = substr($s, 1, -1);
        }
        return stripcslashes($s);
    }
}
