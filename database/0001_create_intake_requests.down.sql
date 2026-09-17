-- Reverses 0001_create_intake_requests. Dropping the table drops its index
-- and destroys every stored request: on a server that has any, back them up
-- first.
DROP TABLE intake_requests;
