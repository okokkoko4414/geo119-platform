CREATE TABLE events (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    event_type VARCHAR(50) NOT NULL,
    session_id UUID NOT NULL,
    user_id UUID,
    page_url TEXT,
    element_selector VARCHAR(500),
    metadata JSONB DEFAULT '{}',
    geo_ip_country VARCHAR(2),
    user_agent_parsed JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (created_at);

CREATE INDEX idx_events_type ON events(event_type);
CREATE INDEX idx_events_session ON events(session_id);
CREATE INDEX idx_events_user ON events(user_id);
CREATE INDEX idx_events_created ON events(created_at);
