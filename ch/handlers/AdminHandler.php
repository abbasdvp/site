<?php

namespace Handlers;

use Core\Database;
use Core\TelegramAPI;
use Models\Admin;
use Models\User;
use Repositories\AdminRepository;
use Repositories\UserRepository;

/**
 * AdminHandler class - Handles all admin-related operations including invite links
 */
class AdminHandler
{
    private TelegramAPI $telegram;
    private AdminRepository $adminRepository;
    private UserRepository $userRepository;

    /**
     * Constructor
     *
     * @param TelegramAPI $telegram
     */
    public function __construct(TelegramAPI $telegram)
    {
        $this->telegram = $telegram;
        $this->adminRepository = new AdminRepository(Database::getInstance());
        $this->userRepository = new UserRepository(Database::getInstance());
    }

    /**
     * Add admin by numeric ID
     *
     * @param array $message
     * @param int $adminId
     * @return bool
     */
    public function addAdminById(array $message, int $adminId): bool
    {
        try {
            $initiatorId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$initiatorId || !$chatId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات کاربر ناقص است.");
                return false;
            }

            // Check if initiator is a super admin
            $initiatorAdmin = $this->adminRepository->findByTelegramId($initiatorId);
            if (!$initiatorAdmin || $initiatorAdmin['role'] !== ADMIN_ROLE_SUPER) {
                $this->telegram->sendMessage($chatId, "❌ فقط ادمین اصلی می‌تواند ادمین جدید اضافه کند.");
                return false;
            }

            // Get user info
            try {
                $userInfo = $this->telegram->getChat($adminId);
            } catch (\Exception $e) {
                $this->telegram->sendMessage($chatId, "❌ کاربر با آیدی {$adminId} یافت نشد یا ربات از آن بلاک شده است.");
                return false;
            }

            // Check if user is already an admin
            $existingAdmin = $this->adminRepository->findByTelegramId($adminId);
            if ($existingAdmin) {
                $this->telegram->sendMessage($chatId, "❌ این کاربر قبلاً به عنوان ادمین ثبت شده است.");
                return false;
            }

            // Create user if doesn't exist
            $user = $this->userRepository->findByTelegramId($adminId);
            if (!$user) {
                $userData = [
                    'telegram_id' => $adminId,
                    'username' => $userInfo['username'] ?? null,
                    'first_name' => $userInfo['first_name'] ?? null,
                    'last_name' => $userInfo['last_name'] ?? null,
                    'created_at' => time()
                ];
                $user = $this->userRepository->create($userData);
            }

            // Create admin record
            $adminData = [
                'user_id' => $user['id'],
                'telegram_id' => $adminId,
                'role' => ADMIN_ROLE_FULL, // Default role
                'invited_by' => $initiatorAdmin['id'],
                'created_at' => time()
            ];

            $adminIdCreated = $this->adminRepository->create($adminData);

            if ($adminIdCreated) {
                $this->telegram->sendMessage($chatId, "✅ ادمین با موفقیت اضافه شد.\n👤 نام: {$userInfo['first_name'] ?? 'N/A'}\n🆔 آیدی: {$adminId}");
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در ذخیره ادمین در دیتابیس.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in addAdminById: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در افزودن ادمین رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Add admin by username
     *
     * @param array $message
     * @param string $username
     * @return bool
     */
    public function addAdminByUsername(array $message, string $username): bool
    {
        try {
            $initiatorId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$initiatorId || !$chatId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات کاربر ناقص است.");
                return false;
            }

            // Normalize username
            if (strpos($username, '@') === 0) {
                $username = substr($username, 1);
            }

            // Check if initiator is a super admin
            $initiatorAdmin = $this->adminRepository->findByTelegramId($initiatorId);
            if (!$initiatorAdmin || $initiatorAdmin['role'] !== ADMIN_ROLE_SUPER) {
                $this->telegram->sendMessage($chatId, "❌ فقط ادمین اصلی می‌تواند ادمین جدید اضافه کند.");
                return false;
            }

            // Get user info by username
            try {
                $userInfo = $this->telegram->getChat("@{$username}");
            } catch (\Exception $e) {
                $this->telegram->sendMessage($chatId, "❌ کاربر با یوزرنیم @{$username} یافت نشد.");
                return false;
            }

            $userId = $userInfo['id'];

            // Check if user is already an admin
            $existingAdmin = $this->adminRepository->findByTelegramId($userId);
            if ($existingAdmin) {
                $this->telegram->sendMessage($chatId, "❌ این کاربر قبلاً به عنوان ادمین ثبت شده است.");
                return false;
            }

            // Create user if doesn't exist
            $user = $this->userRepository->findByTelegramId($userId);
            if (!$user) {
                $userData = [
                    'telegram_id' => $userId,
                    'username' => $userInfo['username'] ?? null,
                    'first_name' => $userInfo['first_name'] ?? null,
                    'last_name' => $userInfo['last_name'] ?? null,
                    'created_at' => time()
                ];
                $user = $this->userRepository->create($userData);
            }

            // Create admin record
            $adminData = [
                'user_id' => $user['id'],
                'telegram_id' => $userId,
                'role' => ADMIN_ROLE_FULL, // Default role
                'invited_by' => $initiatorAdmin['id'],
                'created_at' => time()
            ];

            $adminIdCreated = $this->adminRepository->create($adminData);

            if ($adminIdCreated) {
                $this->telegram->sendMessage($chatId, "✅ ادمین با موفقیت اضافه شد.\n👤 نام: {$userInfo['first_name'] ?? 'N/A'}\n🆔 آیدی: {$userId}\n👤 یوزرنیم: @{$username}");
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در ذخیره ادمین در دیتابیس.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in addAdminByUsername: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در افزودن ادمین رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Generate invite link for admin
     *
     * @param array $message
     * @param string $role
     * @param int|null $validityDays
     * @return bool
     */
    public function generateInviteLink(array $message, string $role = ADMIN_ROLE_FULL, ?int $validityDays = null): bool
    {
        try {
            $initiatorId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$initiatorId || !$chatId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات کاربر ناقص است.");
                return false;
            }

            // Check if initiator is authorized to generate invite links
            $initiatorAdmin = $this->adminRepository->findByTelegramId($initiatorId);
            if (!$initiatorAdmin || 
                ($initiatorAdmin['role'] !== ADMIN_ROLE_SUPER && $role === ADMIN_ROLE_SUPER)) {
                $this->telegram->sendMessage($chatId, "❌ شما مجوز ایجاد لینک دعوت با این سطح دسترسی را ندارید.");
                return false;
            }

            // Generate unique invite code
            $inviteCode = $this->generateUniqueInviteCode();
            
            // Calculate expiration time if validity is set
            $expiresAt = null;
            if ($validityDays) {
                $expiresAt = time() + ($validityDays * ONE_DAY);
            }

            // Create invite link in database
            $inviteData = [
                'user_id' => $initiatorAdmin['user_id'],
                'telegram_id' => $initiatorId,
                'role' => $role,
                'invite_code' => $inviteCode,
                'invited_by' => $initiatorAdmin['id'],
                'expires_at' => $expiresAt,
                'created_at' => time()
            ];

            $adminId = $this->adminRepository->create($inviteData);

            if ($adminId) {
                $inviteLink = "https://t.me/" . BOT_USERNAME . "?start=invite_" . $inviteCode;
                
                $responseMessage = "🔗 لینک دعوت جدید ایجاد شد:\n\n";
                $responseMessage .= "`{$inviteLink}`\n\n";
                $responseMessage .= "👤 سطح دسترسی: {$role}\n";
                
                if ($expiresAt) {
                    $jalaliExpiry = $this->convertTimestampToJalali($expiresAt);
                    $responseMessage .= "📅 انقضا: {$jalaliExpiry['formatted']}\n";
                }
                
                $responseMessage .= "\n⚠️ این لینک را با دقت به افراد مورد اعتماد ارسال کنید.";
                
                $this->telegram->sendMessage($chatId, $responseMessage, parseMode: 'Markdown');
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در ایجاد لینک دعوت.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in generateInviteLink: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در ایجاد لینک دعوت رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Process invite link activation
     *
     * @param array $message
     * @param string $inviteCode
     * @return bool
     */
    public function processInviteActivation(array $message, string $inviteCode): bool
    {
        try {
            $userId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$userId || !$chatId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات کاربر ناقص است.");
                return false;
            }

            // Check if user is already an admin
            $existingAdmin = $this->adminRepository->findByTelegramId($userId);
            if ($existingAdmin) {
                $this->telegram->sendMessage($chatId, "❌ شما قبلاً ادمین ربات هستید.");
                return false;
            }

            // Find the invite code in database
            $inviteRecord = $this->adminRepository->findByInviteCode($inviteCode);

            if (!$inviteRecord) {
                $this->telegram->sendMessage($chatId, "❌ لینک دعوت نامعتبر است یا منقضی شده است.");
                return false;
            }

            // Check if invite has expired
            if ($inviteRecord['expires_at'] && $inviteRecord['expires_at'] < time()) {
                $this->telegram->sendMessage($chatId, "❌ این لینک دعوت منقضی شده است.");
                return false;
            }

            // Create user if doesn't exist
            $user = $this->userRepository->findByTelegramId($userId);
            if (!$user) {
                $userInfo = $this->telegram->getChat($userId);
                $userData = [
                    'telegram_id' => $userId,
                    'username' => $userInfo['username'] ?? null,
                    'first_name' => $userInfo['first_name'] ?? null,
                    'last_name' => $userInfo['last_name'] ?? null,
                    'created_at' => time()
                ];
                $user = $this->userRepository->create($userData);
            }

            // Create admin record with the role from invite
            $adminData = [
                'user_id' => $user['id'],
                'telegram_id' => $userId,
                'role' => $inviteRecord['role'],
                'invited_by' => $inviteRecord['invited_by'],
                'created_at' => time()
            ];

            $adminId = $this->adminRepository->create($adminData);

            if ($adminId) {
                // Delete the used invite code to prevent reuse
                $this->adminRepository->deleteByInviteCode($inviteCode);

                // Send welcome message
                $inviter = $this->adminRepository->findById($inviteRecord['invited_by']);
                $inviterInfo = null;
                if ($inviter) {
                    $inviterInfo = $this->telegram->getChat($inviter['telegram_id']);
                }

                $welcomeMessage = "🎉 به جمع ادمین‌های ربات خوش آمدید!\n";
                $welcomeMessage .= "👤 دعوت‌کننده: " . ($inviterInfo ? "@" . ($inviterInfo['username'] ?? $inviterInfo['first_name']) : "نامشخص") . "\n";
                $welcomeMessage .= "📅 تاریخ: " . $this->convertTimestampToJalali(time())['formatted'] . "\n";
                $welcomeMessage .= "🆔 آیدی شما: {$userId}\n\n";
                $welcomeMessage .= "🔻 سطح دسترسی شما: {$inviteRecord['role']}\n";
                $welcomeMessage .= "از این پس می‌توانید کانال‌های خود را مدیریت کنید.";

                $this->telegram->sendMessage($chatId, $welcomeMessage);
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در فعال‌سازی لینک دعوت.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in processInviteActivation: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در فعال‌سازی لینک دعوت رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Remove admin
     *
     * @param array $message
     * @param int $adminIdToRemove
     * @return bool
     */
    public function removeAdmin(array $message, int $adminIdToRemove): bool
    {
        try {
            $initiatorId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$initiatorId || !$chatId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات کاربر ناقص است.");
                return false;
            }

            // Check if initiator is a super admin
            $initiatorAdmin = $this->adminRepository->findByTelegramId($initiatorId);
            if (!$initiatorAdmin || $initiatorAdmin['role'] !== ADMIN_ROLE_SUPER) {
                $this->telegram->sendMessage($chatId, "❌ فقط ادمین اصلی می‌تواند ادمین دیگری را حذف کند.");
                return false;
            }

            // Check if trying to remove self
            if ($initiatorId === $adminIdToRemove) {
                $this->telegram->sendMessage($chatId, "❌ شما نمی‌توانید خود را حذف کنید.");
                return false;
            }

            // Find admin to remove
            $adminToRemove = $this->adminRepository->findByTelegramId($adminIdToRemove);
            if (!$adminToRemove) {
                $this->telegram->sendMessage($chatId, "❌ ادمین مورد نظر یافت نشد.");
                return false;
            }

            // Remove admin
            $result = $this->adminRepository->deleteByTelegramId($adminIdToRemove);

            if ($result) {
                $this->telegram->sendMessage($chatId, "✅ ادمین با موفقیت حذف شد.");
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در حذف ادمین.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in removeAdmin: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در حذف ادمین رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Change admin role
     *
     * @param array $message
     * @param int $adminId
     * @param string $newRole
     * @return bool
     */
    public function changeAdminRole(array $message, int $adminId, string $newRole): bool
    {
        try {
            $initiatorId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$initiatorId || !$chatId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات کاربر ناقص است.");
                return false;
            }

            // Check if initiator is a super admin
            $initiatorAdmin = $this->adminRepository->findByTelegramId($initiatorId);
            if (!$initiatorAdmin || $initiatorAdmin['role'] !== ADMIN_ROLE_SUPER) {
                $this->telegram->sendMessage($chatId, "❌ فقط ادمین اصلی می‌تواند سطح دسترسی دیگران را تغییر دهد.");
                return false;
            }

            // Validate role
            if (!in_array($newRole, [ADMIN_ROLE_SUPER, ADMIN_ROLE_FULL, ADMIN_ROLE_LIMITED])) {
                $this->telegram->sendMessage($chatId, "❌ سطح دسترسی نامعتبر است.");
                return false;
            }

            // Check if trying to change own role to super
            if ($initiatorId === $adminId && $newRole === ADMIN_ROLE_SUPER) {
                // Allow super admin to set their own role to super
            } else if ($initiatorId !== $adminId && $initiatorAdmin['role'] !== ADMIN_ROLE_SUPER) {
                // Non-super admin cannot change others' roles
                $this->telegram->sendMessage($chatId, "❌ شما مجوز تغییر سطح دسترسی این ادمین را ندارید.");
                return false;
            }

            // Update admin role
            $result = $this->adminRepository->updateByTelegramId($adminId, [
                'role' => $newRole,
                'updated_at' => time()
            ]);

            if ($result > 0) {
                $affectedAdmin = $this->adminRepository->findByTelegramId($adminId);
                $userInfo = $this->telegram->getChat($adminId);
                
                $this->telegram->sendMessage($chatId, "✅ سطح دسترسی ادمین با موفقیت تغییر کرد.\n👤 نام: {$userInfo['first_name'] ?? 'N/A'}\n🔻 سطح جدید: {$newRole}");
                return true;
            } else {
                $this->telegram->sendMessage($chatId, "❌ خطا در تغییر سطح دسترسی ادمین.");
                return false;
            }
        } catch (\Exception $e) {
            error_log("Error in changeAdminRole: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در تغییر سطح دسترسی ادمین رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Get admin list
     *
     * @param array $message
     * @return bool
     */
    public function getAdminList(array $message): bool
    {
        try {
            $initiatorId = $message['from']['id'] ?? null;
            $chatId = $message['chat']['id'] ?? null;

            if (!$initiatorId || !$chatId) {
                $this->telegram->sendMessage($chatId, "❌ اطلاعات کاربر ناقص است.");
                return false;
            }

            // Check if initiator is an admin
            $initiatorAdmin = $this->adminRepository->findByTelegramId($initiatorId);
            if (!$initiatorAdmin) {
                $this->telegram->sendMessage($chatId, "❌ شما ادمین ربات نیستید.");
                return false;
            }

            // Get all admins
            $admins = $this->adminRepository->getAll();

            if (empty($admins)) {
                $this->telegram->sendMessage($chatId, "❌ هیچ ادمینی یافت نشد.");
                return false;
            }

            $adminListMessage = "👥 لیست ادمین‌های ربات:\n\n";
            foreach ($admins as $admin) {
                $userInfo = $this->telegram->getChat($admin['telegram_id']);
                $adminListMessage .= "👤 نام: {$userInfo['first_name'] ?? 'N/A'}\n";
                $adminListMessage .= "🆔 آیدی: {$admin['telegram_id']}\n";
                $adminListMessage .= "👤 یوزرنیم: @" . ($userInfo['username'] ?? 'N/A') . "\n";
                $adminListMessage .= "🔻 سطح: {$admin['role']}\n";
                
                if ($admin['invited_by']) {
                    $inviter = $this->adminRepository->findById($admin['invited_by']);
                    if ($inviter) {
                        $inviterInfo = $this->telegram->getChat($inviter['telegram_id']);
                        $adminListMessage .= "📩 دعوت شده توسط: {$inviterInfo['first_name'] ?? 'N/A'}\n";
                    }
                }
                
                $jalaliDate = $this->convertTimestampToJalali($admin['created_at']);
                $adminListMessage .= "📅 تاریخ عضویت: {$jalaliDate['formatted']}\n\n";
            }

            $this->telegram->sendMessage($chatId, $adminListMessage);
            return true;
        } catch (\Exception $e) {
            error_log("Error in getAdminList: " . $e->getMessage());
            $chatId = $message['chat']['id'] ?? null;
            if ($chatId) {
                $this->telegram->sendMessage($chatId, "❌ خطایی در دریافت لیست ادمین‌ها رخ داده است.");
            }
            return false;
        }
    }

    /**
     * Generate a unique invite code
     *
     * @return string
     */
    private function generateUniqueInviteCode(): string
    {
        do {
            // Generate a random 10-character alphanumeric code
            $code = bin2hex(random_bytes(5)); // 10 hex characters
            $existing = $this->adminRepository->findByInviteCode($code);
        } while ($existing);

        return $code;
    }

    /**
     * Convert timestamp to Jalali date
     *
     * @param int $timestamp
     * @return array
     */
    private function convertTimestampToJalali(int $timestamp): array
    {
        // This is a simplified implementation
        // In a real application, you would use a proper Jalali conversion library
        $date = date('Y/m/d H:i', $timestamp);
        return [
            'formatted' => $date
        ];
    }
}