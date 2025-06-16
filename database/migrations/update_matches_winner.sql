-- Drop the foreign key constraint for winner_id
ALTER TABLE matches
DROP FOREIGN KEY matches_ibfk_5;

-- Add winner_user_id column
ALTER TABLE matches
ADD COLUMN winner_user_id INT,
ADD FOREIGN KEY (winner_user_id) REFERENCES users(id);

-- Drop the winner_id column
ALTER TABLE matches
DROP COLUMN winner_id; 