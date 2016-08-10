<?php

trait LuceneTrait
{
    /**
     * Escape special characters used in Lucene Query Parser Syntax.
     */
    public static function luceneQuery($field, $query) {
        $query = preg_replace(
            '/([*+&|!(){}\[\]^"~*?:\\-])/',
            '\\\\$1',
            $query 
        );
        return "$field:\"$query\"";
    }
}
