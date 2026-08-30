<?php

class Password
{
    public const MIN_MT5_PASSWORD_LENGTH = 8;
    public const MAX_MT5_PASSWORD_LENGTH = 16;

    public const LOWER = 'abcdefghjkmnpqrstuvwxyz';
    public const UPPER = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    public const DIGITAL = '0123456789';
    public const SPECIAL = '#@!$%&*';
    public const MT5_REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[#@!$%&*])[A-Za-z0-9#@!$%&*]{8,16}$/';

    /**
     * 生成符合 MT5 要求的随机密码
     * @param int $length 密码长度，默认12，范围8-16
     * @return string 生成的密码
     * @throws InvalidArgumentException 如果长度不合法
     * @throws RuntimeException|\Random\RandomException 如果生成的密码不符合要求
     */
    public static function generateMt5Password($length = 12)
    {
        if ($length < self::MIN_MT5_PASSWORD_LENGTH) {
            $length = self::MIN_MT5_PASSWORD_LENGTH;
        } elseif ($length > self::MAX_MT5_PASSWORD_LENGTH) {
            $length = self::MAX_MT5_PASSWORD_LENGTH;
        }

        $lower = self::LOWER;
        $upper = self::UPPER;
        $digits = self::DIGITAL;
        $special = self::SPECIAL;

        $all = $lower . $upper . $digits . $special;

        $passwordChars = [
            $lower[random_int(0, strlen($lower) - 1)],
            $upper[random_int(0, strlen($upper) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $special[random_int(0, strlen($special) - 1)],
        ];

        for ($i = count($passwordChars); $i < $length; $i++) {
            $passwordChars[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($i = count($passwordChars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$passwordChars[$i], $passwordChars[$j]] = [$passwordChars[$j], $passwordChars[$i]];
        }

        return implode('', $passwordChars);
    }

    /**
     * 验证密码是否符合 MT5 要求
     * @param string $password 待验证的密码
     * @return bool 是否符合要求
     */
    public static function validateMt5Password($password)
    {
        return preg_match(self::MT5_REGEX, $password) === 1;
    }

    /**
     * 生成一个随机密码，包含大小写字母、数字和特殊字符，长度可指定
     * @param int $length 密码长度，默认12
     * @return string 生成的密码
     * @throws RuntimeException|\Random\RandomException 如果生成的密码不符合要求
     */
    public static function generateRandomPassword($length = 12)
    {
        $characters = self::LOWER . self::UPPER . self::DIGITAL . self::SPECIAL;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $password;
    }
}
