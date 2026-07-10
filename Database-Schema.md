Table customers {
  id bigint [pk, increment]
  reg_id varchar [unique, not null]
  full_name varchar [not null]
  identity_type varchar
  identity_number varchar
  gender varchar
  email varchar
  phone varchar [not null]
  registration_date date [not null]
  customer_status varchar [not null]
  created_at timestamp
  updated_at timestamp
}

Table customer_addresses {
  id bigint [pk, increment]
  customer_id bigint [not null]
  address text [not null]
  village varchar
  district varchar
  city varchar
  latitude decimal(10,7)
  longitude decimal(10,7)
  location_note text
  created_at timestamp
  updated_at timestamp
}

Table customer_documents {
  id bigint [pk, increment]
  customer_id bigint [not null]
  document_type varchar [not null]
  file_path varchar [not null]
  description text
  created_at timestamp
  updated_at timestamp
}

Table sales {
  id bigint [pk, increment]
  sales_code varchar [unique, not null]
  name varchar [not null]
  phone varchar
  status varchar
  created_at timestamp
  updated_at timestamp
}

Table agents {
  id bigint [pk, increment]
  agent_code varchar [unique, not null]
  name varchar [not null]
  phone varchar
  status varchar
  created_at timestamp
  updated_at timestamp
}

Table staff {
  id bigint [pk, increment]
  staff_code varchar [unique, not null]
  name varchar [not null]
  role varchar [not null]
  phone varchar
  status varchar
  created_at timestamp
  updated_at timestamp
}

Table customer_referrals {
  id bigint [pk, increment]
  customer_id bigint [not null]
  sales_id bigint
  agent_id bigint
  referral_customer_id bigint
  referral_type varchar
  note text
  created_at timestamp
  updated_at timestamp
}

Table internet_packages {
  id bigint [pk, increment]
  package_code varchar [unique, not null]
  name varchar [not null]
  price decimal(15,2) [not null]
  upload_speed int
  download_speed int
  profile_name varchar
  contract_type varchar
  is_active boolean
  created_at timestamp
  updated_at timestamp
}

Table customer_subscriptions {
  id bigint [pk, increment]
  customer_id bigint [not null]
  package_id bigint [not null]
  billing_start_date date
  discount_amount decimal(15,2)
  tax_percent decimal(5,2)
  monthly_total_snapshot decimal(15,2)
  subscription_status varchar [not null]
  created_at timestamp
  updated_at timestamp
}

Table surveys {
  id bigint [pk, increment]
  customer_id bigint [not null]
  scheduled_at datetime
  started_at datetime
  finished_at datetime
  duration_minutes int
  house_photo_path varchar
  equipment_needs text
  survey_status varchar [not null]
  notes text
  created_at timestamp
  updated_at timestamp
}

Table survey_staff {
  id bigint [pk, increment]
  survey_id bigint [not null]
  staff_id bigint [not null]
  role varchar
}

Table fop_assignments {
  id bigint [pk, increment]
  customer_id bigint [not null]
  fop_id bigint [not null]
  survey_assigned_at datetime
  installation_assigned_at datetime
  assignment_status varchar
  notes text
  created_at timestamp
  updated_at timestamp
}

Table installations {
  id bigint [pk, increment]
  customer_id bigint [not null]
  scheduled_at datetime
  started_at datetime
  finished_at datetime
  installation_status varchar [not null]
  notes text
  created_at timestamp
  updated_at timestamp
}

Table installation_technicians {
  id bigint [pk, increment]
  installation_id bigint [not null]
  staff_id bigint [not null]
  role varchar
}

Table activations {
  id bigint [pk, increment]
  customer_id bigint [not null]
  activated_at datetime
  activated_by bigint
  activation_status varchar [not null]
  notes text
  created_at timestamp
  updated_at timestamp
}

Table branches {
  id bigint [pk, increment]
  branch_code varchar [unique, not null]
  name varchar [not null]
  address text
  created_at timestamp
  updated_at timestamp
}

Table pops {
  id bigint [pk, increment]
  branch_id bigint [not null]
  pop_code varchar [unique, not null]
  name varchar [not null]
  location text
  created_at timestamp
  updated_at timestamp
}

Table olts {
  id bigint [pk, increment]
  pop_id bigint [not null]
  olt_code varchar [unique, not null]
  name varchar [not null]
  brand varchar
  model varchar
  ip_address varchar
  created_at timestamp
  updated_at timestamp
}

Table olt_ports {
  id bigint [pk, increment]
  olt_id bigint [not null]
  port_number varchar [not null]
  status varchar
  created_at timestamp
  updated_at timestamp
}

Table odps {
  id bigint [pk, increment]
  pop_id bigint [not null]
  odp_code varchar [unique, not null]
  name varchar
  location text
  latitude decimal(10,7)
  longitude decimal(10,7)
  created_at timestamp
  updated_at timestamp
}

Table odp_ports {
  id bigint [pk, increment]
  odp_id bigint [not null]
  port_number varchar [not null]
  status varchar
  created_at timestamp
  updated_at timestamp
}

Table routers {
  id bigint [pk, increment]
  router_code varchar [unique, not null]
  name varchar [not null]
  ip_address varchar
  location text
  created_at timestamp
  updated_at timestamp
}

Table vlans {
  id bigint [pk, increment]
  vlan_code varchar [unique, not null]
  vlan_number int [not null]
  name varchar
  description text
  created_at timestamp
  updated_at timestamp
}

Table cid_inventory {
  id bigint [pk, increment]
  cid_code varchar [unique, not null]
  status varchar [not null]
  current_customer_id bigint
  allocated_at datetime
  withdrawn_at datetime
  notes text
  created_at timestamp
  updated_at timestamp
}

Table technical_profiles {
  id bigint [pk, increment]
  customer_id bigint [not null]
  subscription_id bigint
  cid_id bigint
  ip_address varchar
  sn varchar
  passive_device text
  branch_id bigint
  pop_id bigint
  olt_id bigint
  olt_port_id bigint
  odp_id bigint
  odp_port_id bigint
  router_id bigint
  vlan_id bigint
  attenuation_initial decimal(5,2)
  attenuation_current decimal(5,2)
  technical_notes text
  is_current boolean
  valid_from datetime
  valid_until datetime
  created_at timestamp
  updated_at timestamp
}

Table test_reports {
  id bigint [pk, increment]
  customer_id bigint [not null]
  installation_id bigint
  tested_at datetime [not null]
  attenuation_signal decimal(5,2)
  speedtest_photo_path varchar
  jitter_ms decimal(8,2)
  latency_ms decimal(8,2)
  upload_speed decimal(8,2)
  download_speed decimal(8,2)
  packet_loss_percent decimal(5,2)
  package_match_percent decimal(5,2)
  quality_score decimal(5,2)
  tested_by bigint
  notes text
  created_at timestamp
  updated_at timestamp
}

Table initial_invoices {
  id bigint [pk, increment]
  customer_id bigint [not null]
  invoice_number varchar [unique, not null]
  invoice_date date [not null]
  due_date date
  total_amount decimal(15,2)
  payment_status varchar [not null]
  created_at timestamp
  updated_at timestamp
}

Table initial_invoice_items {
  id bigint [pk, increment]
  invoice_id bigint [not null]
  item_name varchar [not null]
  description text
  quantity decimal(10,2)
  unit_price decimal(15,2)
  subtotal decimal(15,2)
  created_at timestamp
  updated_at timestamp
}

Table payments {
  id bigint [pk, increment]
  invoice_id bigint [not null]
  payment_date date
  amount decimal(15,2)
  payment_method varchar
  payment_proof_path varchar
  payment_note text
  created_at timestamp
  updated_at timestamp
}

Ref: customer_addresses.customer_id > customers.id
Ref: customer_documents.customer_id > customers.id

Ref: customer_referrals.customer_id > customers.id
Ref: customer_referrals.sales_id > sales.id
Ref: customer_referrals.agent_id > agents.id
Ref: customer_referrals.referral_customer_id > customers.id

Ref: customer_subscriptions.customer_id > customers.id
Ref: customer_subscriptions.package_id > internet_packages.id

Ref: surveys.customer_id > customers.id
Ref: survey_staff.survey_id > surveys.id
Ref: survey_staff.staff_id > staff.id

Ref: fop_assignments.customer_id > customers.id
Ref: fop_assignments.fop_id > staff.id

Ref: installations.customer_id > customers.id
Ref: installation_technicians.installation_id > installations.id
Ref: installation_technicians.staff_id > staff.id

Ref: activations.customer_id > customers.id
Ref: activations.activated_by > staff.id

Ref: pops.branch_id > branches.id
Ref: olts.pop_id > pops.id
Ref: olt_ports.olt_id > olts.id
Ref: odps.pop_id > pops.id
Ref: odp_ports.odp_id > odps.id

Ref: cid_inventory.current_customer_id > customers.id

Ref: technical_profiles.customer_id > customers.id
Ref: technical_profiles.subscription_id > customer_subscriptions.id
Ref: technical_profiles.cid_id > cid_inventory.id
Ref: technical_profiles.branch_id > branches.id
Ref: technical_profiles.pop_id > pops.id
Ref: technical_profiles.olt_id > olts.id
Ref: technical_profiles.olt_port_id > olt_ports.id
Ref: technical_profiles.odp_id > odps.id
Ref: technical_profiles.odp_port_id > odp_ports.id
Ref: technical_profiles.router_id > routers.id
Ref: technical_profiles.vlan_id > vlans.id

Ref: test_reports.customer_id > customers.id
Ref: test_reports.installation_id > installations.id
Ref: test_reports.tested_by > staff.id

Ref: initial_invoices.customer_id > customers.id
Ref: initial_invoice_items.invoice_id > initial_invoices.id
Ref: payments.invoice_id > initial_invoices.id