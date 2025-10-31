<?php
/**
 * Attendance Model
 * 
 * @package HR3
 * @subpackage Models
 */

declare(strict_types=1);

namespace HR3\Models;

use PDO;

class Attendance extends BaseModel
{
    protected string $table = 'attendance_logs';
    protected string $primaryKey = 'attendance_id';

    /**
     * Get attendance by user and date range
     */
    public function getByUserAndDateRange(int $userId, string $startDate, string $endDate): array
    {
        $sql = "SELECT a.*, u.first_name, u.last_name 
                FROM {$this->table} a
                JOIN users u ON a.user_id = u.user_id
                WHERE a.user_id = :user_id 
                AND DATE(a.clock_in) BETWEEN :start_date AND :end_date
                AND a.is_archived = 0
                ORDER BY a.clock_in DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get today's active clock-in for user
     */
    public function getTodayActiveClockIn(int $userId): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id 
                AND DATE(clock_in) = CURDATE()
                AND clock_out IS NULL
                AND is_archived = 0
                ORDER BY clock_in DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Clock in - with custom time support
     */
    public function clockIn(int $userId, ?string $location = null, ?string $deviceType = null, ?string $clockInTime = null): int|false
    {
        // Use provided time or current time
        $time = $clockInTime ?? date('Y-m-d H:i:s');
        
        $data = [
            'user_id' => $userId,
            'clock_in' => $time,
            'status' => 'Present',
            'location' => $location,
            'device_type' => $deviceType
        ];
        
        return $this->create($data);
    }

    /**
     * Clock out
     */
    public function clockOut(int $attendanceId): bool
    {
        $attendance = $this->find($attendanceId);
        if (!$attendance || $attendance['clock_out']) {
            return false;
        }

        $clockIn = strtotime($attendance['clock_in']);
        $clockOut = time();
        $totalHours = round(($clockOut - $clockIn) / 3600, 2);

        return $this->update($attendanceId, [
            'clock_out' => date('Y-m-d H:i:s'),
            'total_hours' => $totalHours
        ]);
    }

    /**
     * Archive attendance record
     */
    public function archive(int $attendanceId): bool
    {
        return $this->update($attendanceId, [
            'is_archived' => 1,
            'archived_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Restore archived record
     */
    public function restore(int $attendanceId): bool
    {
        return $this->update($attendanceId, [
            'is_archived' => 0,
            'archived_at' => null
        ]);
    }

    /**
     * Get attendance statistics
     */
    public function getStatistics(int $userId, string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                COUNT(*) as total_days,
                SUM(total_hours) as total_hours,
                AVG(total_hours) as avg_hours,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
                FROM {$this->table}
                WHERE user_id = :user_id
                AND DATE(clock_in) BETWEEN :start_date AND :end_date
                AND is_archived = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all attendance for management (with filters)
     */
    public function getAllWithFilters(array $filters = []): array
    {
        $sql = "SELECT a.*, u.first_name, u.last_name, d.department_name
                FROM {$this->table} a
                JOIN users u ON a.user_id = u.user_id
                JOIN departments d ON u.department_id = d.department_id
                WHERE a.is_archived = 0";
        
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND a.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND DATE(a.clock_in) >= :start_date";
            $params[':start_date'] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND DATE(a.clock_in) <= :end_date";
            $params[':end_date'] = $filters['end_date'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        $sql .= " ORDER BY a.clock_in DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
        }
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if (!empty($filters['limit'])) {
            $stmt->bindValue(':limit', (int)$filters['limit'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}