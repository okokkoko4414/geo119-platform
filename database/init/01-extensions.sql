CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

DO $$
BEGIN
    RAISE NOTICE 'Extensions loaded: vector=%, pg_trgm=%, uuid-ossp=%',
        (SELECT extversion FROM pg_extension WHERE extname = 'vector'),
        (SELECT extversion FROM pg_extension WHERE extname = 'pg_trgm'),
        (SELECT extversion FROM pg_extension WHERE extname = 'uuid-ossp');
END $$;
