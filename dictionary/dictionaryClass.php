<?php

class dictionaryClass
{
    private static $words = [
        'msg' => [
            'success' => '%s realizado com sucesso',
            'empty' => 'Nenhuma migração para ser executada',
            'onlyFailed' => 'Nenhuma das migrações foram executadas',
        ],
        'error' => [
            'persistence' => "Não foi possível persistir a migração %s, SQL: %s",
            'notAuthorized' => "Não é permitido executar esse tipo de SQL. SQL: %s",
            'directory' => "O diretório informado em '%s' não é um diretório válido."
        ]
    ];

    /**
     * @param $key
     * @param array $params
     * @return string
     * @throws MigrationException
     */
    public static function dictionary($key, $params = [])
    {
        if (strpos($key, '.')) {
            $stacks = explode('.', $key);
            $result = self::$words;
            foreach ($stacks as $index => $stack) {
                $result = $result[$stack];
            }
        } else {
            $result = self::$words[$key];
        }

        if (is_array($result))
            throw new MigrationException("Palavra ou nível não encontrado");
        return ucfirst(self::withParamsReplaced($result, $params));
    }

    /**
     * @param $result
     * @param array $params
     * @return string
     */
    private static function withParamsReplaced($result, array $params)
    {
        foreach ($params as $index => $param) {
            $result = self::str_replace_first('%s', $param, $result);
        }

        return $result;
    }

    private static function str_replace_first($from, $to, $content)
    {
        $from = '/' . preg_quote($from, '/') . '/';

        return preg_replace($from, $to, $content, 1);
    }

}
