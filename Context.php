<?

namespace gi\parse;

class Context {
    public $resultstack = [];

    function pushResult($mixed) {
        array_push($this->resultstack, $mixed);
    }

    function popResult() {
        return array_pop($this->resultstack);
    }

    function resultCount() {
        return count($this->resultstack);
    }

    function peekResult() {
        if (empty($this->resultstack)) {
            throw new \Exception("empty resultstack");
        }
        return $this->resultstack[count($this->resultstack) - 1];
    }
}