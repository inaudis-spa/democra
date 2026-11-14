-- Allow invitation dispatch records to follow their owning election/member lifecycle.
-- Apply after 20261104_invitation_dispatch.sql.
ALTER TABLE invitation_delivery_events
  DROP FOREIGN KEY fk_invitation_event_delivery;
ALTER TABLE invitation_delivery_events
  ADD CONSTRAINT fk_invitation_event_delivery
  FOREIGN KEY (delivery_id) REFERENCES invitation_deliveries(id) ON DELETE CASCADE;

ALTER TABLE invitation_deliveries
  DROP FOREIGN KEY fk_invitation_delivery_campaign,
  DROP FOREIGN KEY fk_invitation_delivery_member;
ALTER TABLE invitation_deliveries
  ADD CONSTRAINT fk_invitation_delivery_campaign
    FOREIGN KEY (campaign_id) REFERENCES invitation_campaigns(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_invitation_delivery_member
    FOREIGN KEY (census_member_id) REFERENCES census_members(id) ON DELETE CASCADE;

ALTER TABLE invitation_campaigns
  DROP FOREIGN KEY fk_invitation_campaign_election;
ALTER TABLE invitation_campaigns
  ADD CONSTRAINT fk_invitation_campaign_election
  FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE;
