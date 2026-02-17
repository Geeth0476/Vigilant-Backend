<?php
// core/validation.php

class Validator {
    public static function validate($data, $rules) {
        foreach ($rules as $field => $ruleStr) {
            $rulesArr = explode('|', $ruleStr);
            if (!isset($data[$field]) && in_array('required', $rulesArr)) {
                return "Field '$field' is required.";
            }
            if (isset($data[$field])) {
                $value = $data[$field];
                foreach ($rulesArr as $rule) {
                    if ($rule === 'email') {
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            return "Field '$field' must be a valid email.";
                        }
                    }
                    if ($rule === 'numeric') {
                        if (!is_numeric($value)) {
                            return "Field '$field' must be numeric.";
                        }
                    }
                    // Add more rules as needed...
                }
            }
        }
        return null; // No errors
    }
}
?>
