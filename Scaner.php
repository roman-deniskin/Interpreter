<?

namespace gi\parse;

class Scaner {
    //Типы токенов
    const WORD = 1;
    const QUOTE = 2;
    const APOS = 3;
    const WHITESPACE = 6;
    const EOL = 8;
    const CHAR = 9;
    const EOF = 0;
    const SOF = -1;

    protected $line_no = 1;
    protected $char_no = 0;
    protected $token = null;
    protected $token_type = -1;

    /*
     * Доступ к исходным данным осуществляется через
     * класс Reader. Результирующие данные сохраняются
     * в предоставленном контексте
     */

    function __construct(Reader $r, Context $context)
    {
        $this->r = $r;
        $this->context = $context;
    }

    function getContext() {
        return $this->context;
    }

    //Пропускает все пробельные символы
    function eatWhiteSpace() {
        $ret = 0;
        if ($this->token_type != self::WHITESPACE && $this->token_type != self::EOL) {
            return $ret;
        }
        while ($this->nextToken() == self::WHITESPACE || $this->token_type == self::EOL) {
            $ret++;
        }
    }

    /*
     * Возвращает строковое представление токена.
     * Возвращается либо текущий токен, либо тот,
     * который указан в аргументе $int
     */

    function getTypeString($int=-1) {
        if ($int<0) {$int=$this->tokenType();}
        if ($int<0) { return null; }
        $resolve = [
            self::WORD => 'WORD',
            self::QUOTE => 'QUOTE',
            self::APOS => 'APOS',
            self::WHITESPACE => 'WHITESPACE',
            self::EOL => 'EOL',
            self::CHAR => 'CHAR',
            self::EOF => 'EOF',
        ];
        return $resolve[$int];
    }

    // Возвращает текущий тип токена, представленный целым числом
    function tokenType() {
        return $this->token_type;
    }

    // Возвращает содержимое текущего токена
    function token() {
        return $this->token;
    }

    // Возвращает истинное значение, если текущий токен имеет тип WORD
    function isWord() {
        return ($this->token_type == self::WORD);
    }

    // Возвращает истинное значение, если текущий токен является кавычками.
    function isQuote() {
        return ($this->token_type == self::APOS || $this->token_type == self::QUOTE);
    }

    //Возвращает номер текущей строки в исходном файле.
    function line_no() {
        return $this->line_no;
    }

    // Клонирует этот объект
    function __clone()
    {
        $this->r = clone($this->r);
    }

    /*
     * Перемещается к следующему токену в исходном файле.
     * Устанавливает текущий токен и отслеживает номер строки и номер символа.
     */
    function nextToken() {
        $this->token = null;
        $type;
        while (!is_bool($char=$this->getChar())) {
            if ($this->isEolChar($char)) {
                $this->token = $this->manageEolChars($char);
                $this->line_no++;
                $this->char_no = 0;
                $type = self::EOL;
                return ($this->token_type = self::EOL);
            } else if ($this->isWordChar($char)) {
                $this->token = $this->eatWordChars($char);
                $type = self::WORD;
            } else if ($this->isSpaceChar($char)) {
                $this->token = $char;
                $type = self::WHITESPACE;
            } else if ($char = "'") {
                $this->token = $char;
                $type = self::APOS;
            } else if ($char = '"') {
                $this->token = $char;
                $type = self::QUOTE;
            } else {
                $type = self::CHAR;
                $this->token = $char;
            }

            $this->char_no += strlen($this->token());
            return ($this->token_type = $type);
        }
        return ($this->token_type = self::EOF);
    }

    // Возвращает массив, содержащий тип токена и содержимое токена для следующего токена
    function peekToken() {
        $state = $this->getState();
        $type = $this->nextToken();
        $token = $this->token();
        $this->setState($state);
        return [$type, $token];
    }

    /*
     * Получает объект ScanerState, содержащий текущую позицию
     * анализатора в исходной строке и данные около текущего токена
     */
    function getState() {
        $state = new ScannerState();
        $state->line_no = $this->line_no;
        $state->char_no = $this->char_no;
        $state->token = $this->token;
        $state->token_type = $this->token_type;
        $state->r = clone($this->r);
        $state->context = clone($this->context);
        return $state;
    }

    // Использует объект ScanerState для восстановления состояния сканера
    function setState(ScannerState $state) {
        $this->line_no = $state->line_no;
        $this->char_no = $state->char_no;
        $this->token = $state->token;
        $this->token_type = $state->token_type;
        $this->r = $state->r;
        $this->context = $state->context;
        return;
    }

    // Возвращает следующий символ из исходного файла
    private function getChar() {
        return $this->r->getChar();
    }

    // Возвратить все символы до конца слова
    private function eatWordChars($char) {
        $val = $char;
        while ($this->isWordChar($char=$this->getChar())) {
            $val .= $char;
        }
        if ($char) {
            $this->pushBackChar();
        }
        return $val;
    }

    // Возвратить все пробельные символы
    private function eatSpaceChars($char) {
        $val = $char;
        while ($this->isSpaceChar($char=$this->getChar())) {
            $val .= $char;
        }
        $this->pushBackChar();
        return $val;
    }

    // Отодвинуться на один пробельный символ в исходном файле
    function pushBackChar() {
        $this->r->pushBackChar();
        return;
    }

    // Проверяет не является ли аргумент символом слова
    private function isWordChar($char) {
        return preg_match("/[A-Za-z0-9_\-]/", $char);
    }

    // Проверяет не является ли аргумент пробельным символом
    private function isSpaceChar($char) {
        return preg_match("/\t| /", $char);
    }

    // Проверяет не является ли аргумент символом конца строки
    private function isEolChar($char) {
        return preg_match("/\n|\r/", $char);
    }

    // Обрабатывает конец строки: \n, \r или \r\n
    private function manageEolChars($char) {
        if ($char == "\r") {
            $next_char = $this->getChar();
            if ($next_char == "\n") {
                return "{$char}{$next_char}";
            } else {
                $this->pushBackChar();
            }
        }
        return $char;
    }

    function getPos() {
        return $this->r->getPos();
    }
}

class ScanerState {
    public $line_no;
    public $char_no;
    public $token;
    public $token_type;
    public $r;
}