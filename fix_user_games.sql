-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS before_user_games_insert;
DROP TRIGGER IF EXISTS before_user_games_update;

-- Update any existing records to ensure only one primary game per user
UPDATE user_games ug1
JOIN (
    SELECT user_id, MIN(id) as first_game_id
    FROM user_games
    GROUP BY user_id
) ug2 ON ug1.user_id = ug2.user_id
SET ug1.is_primary = CASE 
    WHEN ug1.id = ug2.first_game_id THEN 1 
    ELSE 0 
END; 