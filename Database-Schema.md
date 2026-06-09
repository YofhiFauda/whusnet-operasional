Enum gender_enum {
  laki_laki
  perempuan
}

Enum staff_role_enum {
  sales
  agent
  surveyor
  technician
  activator
  fop
  admin
}

Enum customer_status_enum {
  registered
  waiting_survey
  surveyed
  waiting_installation
  installed
  active
  suspended
  terminated
  rejected
}

Enum document_type_enum {
  ktp
  rumah
  kontrak
}

Enum contract_type_enum {
  bulanan
  tahunan
  kontrak_khusus
}

Enum discount_type_enum {
  none
  percent
  fixed
}

Enum subscription_status_enum {
  registered
  survey_process
  survey_done
  installation_process
  installed
  active
  suspended
  terminated
  rejected
}

Enum survey_status_enum {
  scheduled
  in_progress
  completed
  cancelled
}

Enum fop_status_enum {
  assigned
  survey_assigned
  installation_assigned
  completed
  cancelled
}

Enum installation_status_enum {
  scheduled
  in_progress
  completed
  failed
  cancelled
}

Enum activation_status_enum {
  pending
  active
  failed
  cancelled
}

Enum cid_status_enum {
  available
  assigned
  reserved
  withdrawn
  damaged
}

Enum cid_assignment_status_enum {
  active
  released
  cancelled
}

Enum invoice_status_enum {
  draft
  unpaid
  partially_paid
  paid
  cancelled
}

Enum initial_invoice_item_type_enum {
  installation_fee
  prorate
  cable
  additional_device_installation
  additional_pole
  other
}

Table cities {
  id int [pk, increment]
  name varchar(100) [not null]

  created_at timestamp
  updated_at timestamp
}

Table districts {
  id int [pk, increment]
  city_id int [not null]
  name varchar(100) [not null]

  created_at timestamp
  updated_at timestamp
}

Table villages {
  id int [pk, increment]
  district_id int [not null]
  name varchar(100) [not null]

  created_at timestamp
  updated_at timestamp
}

Table staff {
  id int [pk, increment]
  staff_code varchar(50) [not null, unique]
  full_name varchar(150) [not null]
  email varchar(150)
  phone_number varchar(30)
  role staff_role_enum [not null]
  is_active boolean [default: true]

  created_at timestamp
  updated_at timestamp
}

Table customers {
  id int [pk, increment]

  // ID REG permanen dari aplikasi
  registration_code varchar(50) [not null, unique]

  full_name varchar(150) [not null]
  identity_number varchar(50) [not null, unique]
  gender gender_enum
  email varchar(150)
  phone_number varchar(30) [not null]

  address text [not null]
  village_id int
  latitude decimal(10,8)
  longitude decimal(11,8)

  registration_date date [not null]
  status customer_status_enum [not null, default: 'registered']

  created_at timestamp
  updated_at timestamp

  indexes {
    registration_code [unique]
    identity_number [unique]
    phone_number
    email
  }
}

Table customer_documents {
  id int [pk, increment]
  customer_id int [not null]

  document_type document_type_enum [not null]
  file_url text [not null]
  uploaded_at timestamp
  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    (customer_id, document_type)
  }
}

Table service_packages {
  id int [pk, increment]
  package_code varchar(50) [not null, unique]

  name varchar(150) [not null]
  monthly_price decimal(12,2) [not null]

  upload_speed_mbps decimal(8,2) [not null]
  download_speed_mbps decimal(8,2) [not null]

  // Contoh: profile PPPoE / profile MikroTik / profile billing
  profile varchar(100)

  is_active boolean [default: true]

  created_at timestamp
  updated_at timestamp
}

Table customer_subscriptions {
  id int [pk, increment]
  subscription_code varchar(50) [not null, unique]

  customer_id int [not null]
  service_package_id int [not null]

  contract_type contract_type_enum [not null]

  // Snapshot harga paket saat pelanggan daftar
  package_price decimal(12,2) [not null]

  discount_type discount_type_enum [default: 'none']
  discount_value decimal(12,2) [default: 0]
  discount_amount decimal(12,2) [default: 0]

  ppn_percent decimal(5,2) [default: 0]
  ppn_amount decimal(12,2) [default: 0]

  // Total biaya layanan bulanan setelah diskon + PPN
  total_service_fee decimal(12,2) [not null]

  status subscription_status_enum [not null, default: 'registered']

  subscribed_at timestamp
  activated_at timestamp
  terminated_at timestamp

  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    subscription_code [unique]
    customer_id
    service_package_id
    status
  }
}

Table subscription_referrals {
  id int [pk, increment]

  // Referral mengikuti proses langganan, bukan hanya data pelanggan
  subscription_id int [not null, unique]

  sales_staff_id int
  agent_staff_id int
  referral_customer_id int

  notes text

  created_at timestamp
  updated_at timestamp
}

Table surveys {
  id int [pk, increment]
  survey_code varchar(50) [not null, unique]

  subscription_id int [not null]

  start_at timestamp
  end_at timestamp

  house_photo_url text
  equipment_needs text

  duration_minutes int
  status survey_status_enum [not null, default: 'scheduled']

  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    survey_code [unique]
    subscription_id
    status
  }
}

Table survey_staff {
  id int [pk, increment]

  survey_id int [not null]
  staff_id int [not null]

  role_in_survey varchar(100)
  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    (survey_id, staff_id) [unique]
  }
}

Table fop_jobs {
  id int [pk, increment]
  fop_code varchar(50) [not null, unique]

  subscription_id int [not null]
  survey_id int

  // ID FOP / petugas FOP
  fop_staff_id int [not null]

  survey_assigned_at timestamp
  installation_assigned_at timestamp

  status fop_status_enum [not null, default: 'assigned']
  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    fop_code [unique]
    subscription_id
    fop_staff_id
  }
}

Table installations {
  id int [pk, increment]
  installation_code varchar(50) [not null, unique]

  subscription_id int [not null]
  fop_job_id int

  start_at timestamp
  end_at timestamp

  status installation_status_enum [not null, default: 'scheduled']

  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    installation_code [unique]
    subscription_id
    status
  }
}

Table installation_technicians {
  id int [pk, increment]

  installation_id int [not null]
  staff_id int [not null]

  role_in_installation varchar(100)
  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    (installation_id, staff_id) [unique]
  }
}

Table activations {
  id int [pk, increment]
  activation_code varchar(50) [not null, unique]

  subscription_id int [not null]
  installation_id int [not null]

  activated_at timestamp
  activated_by_staff_id int [not null]

  status activation_status_enum [not null, default: 'pending']

  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    activation_code [unique]
    subscription_id
    installation_id
    status
  }
}

Table master_cids {
  id int [pk, increment]

  cid_code varchar(100) [not null, unique]
  status cid_status_enum [not null, default: 'available']

  // Nullable. Jika pelanggan putus langganan, CID dilepas dan kembali ke stock.
  current_subscription_id int

  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    cid_code [unique]
    status
  }
}

Table cid_assignments {
  id int [pk, increment]

  cid_id int [not null]
  subscription_id int [not null]

  assigned_at timestamp [not null]
  released_at timestamp

  status cid_assignment_status_enum [not null, default: 'active']

  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    cid_id
    subscription_id
    status
  }
}

Table customer_technical_data {
  id int [pk, increment]

  subscription_id int [not null, unique]
  cid_assignment_id int

  // IP address dial-up
  ip_address varchar(50)

  // Serial Number perangkat
  serial_number varchar(100)

  passive_device text

  branch_number varchar(50)
  pop_number varchar(50)

  olt_number varchar(50)
  olt_port_number varchar(50)

  odp_number varchar(50)
  odp_port_number varchar(50)

  router_number varchar(50)

  initial_attenuation_dbm decimal(8,2)
  current_attenuation_dbm decimal(8,2)

  vlan varchar(50)

  technical_notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    subscription_id [unique]
    ip_address
    serial_number
    vlan
  }
}

Table test_reports {
  id int [pk, increment]
  test_code varchar(50) [not null, unique]

  subscription_id int [not null]
  installation_id int [not null]

  tested_at timestamp [not null]

  initial_attenuation_dbm decimal(8,2)

  speedtest_photo_url text

  jitter_ms decimal(8,2)
  latency_ms decimal(8,2)

  upload_speed_mbps decimal(8,2)
  download_speed_mbps decimal(8,2)

  packet_loss_percent decimal(5,2)

  // Contoh paket 10 Mbps, hasil 9 Mbps = 90%
  package_match_percent decimal(5,2)

  // Bisa dihitung otomatis dari speed, jitter, latency, packet loss
  quality_score decimal(5,2)

  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    test_code [unique]
    subscription_id
    installation_id
  }
}

Table initial_invoices {
  id int [pk, increment]

  invoice_number varchar(50) [not null, unique]

  subscription_id int [not null]
  activation_id int

  invoice_date date [not null]
  due_date date

  installation_fee decimal(12,2) [default: 0]

  // Prorate dihitung dari tanggal aktivasi sampai akhir bulan
  prorate_start_date date
  prorate_end_date date
  prorate_days int
  prorate_amount decimal(12,2) [default: 0]

  other_fee_total decimal(12,2) [default: 0]

  subtotal decimal(12,2) [default: 0]
  total_amount decimal(12,2) [not null]

  status invoice_status_enum [not null, default: 'draft']
  paid_at timestamp

  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    invoice_number [unique]
    subscription_id
    status
  }
}

Table initial_invoice_items {
  id int [pk, increment]

  initial_invoice_id int [not null]

  item_type initial_invoice_item_type_enum [not null]

  description varchar(255) [not null]

  quantity decimal(10,2) [default: 1]
  unit_price decimal(12,2) [default: 0]
  amount decimal(12,2) [not null]

  notes text

  created_at timestamp
  updated_at timestamp

  indexes {
    initial_invoice_id
    item_type
  }
}

/*
|--------------------------------------------------------------------------
| RELATIONS
|--------------------------------------------------------------------------
*/

Ref: districts.city_id > cities.id
Ref: villages.district_id > districts.id

Ref: customers.village_id > villages.id

Ref: customer_documents.customer_id > customers.id

Ref: customer_subscriptions.customer_id > customers.id
Ref: customer_subscriptions.service_package_id > service_packages.id

Ref: subscription_referrals.subscription_id > customer_subscriptions.id
Ref: subscription_referrals.sales_staff_id > staff.id
Ref: subscription_referrals.agent_staff_id > staff.id
Ref: subscription_referrals.referral_customer_id > customers.id

Ref: surveys.subscription_id > customer_subscriptions.id

Ref: survey_staff.survey_id > surveys.id
Ref: survey_staff.staff_id > staff.id

Ref: fop_jobs.subscription_id > customer_subscriptions.id
Ref: fop_jobs.survey_id > surveys.id
Ref: fop_jobs.fop_staff_id > staff.id

Ref: installations.subscription_id > customer_subscriptions.id
Ref: installations.fop_job_id > fop_jobs.id

Ref: installation_technicians.installation_id > installations.id
Ref: installation_technicians.staff_id > staff.id

Ref: activations.subscription_id > customer_subscriptions.id
Ref: activations.installation_id > installations.id
Ref: activations.activated_by_staff_id > staff.id

Ref: master_cids.current_subscription_id > customer_subscriptions.id

Ref: cid_assignments.cid_id > master_cids.id
Ref: cid_assignments.subscription_id > customer_subscriptions.id

Ref: customer_technical_data.subscription_id > customer_subscriptions.id
Ref: customer_technical_data.cid_assignment_id > cid_assignments.id

Ref: test_reports.subscription_id > customer_subscriptions.id
Ref: test_reports.installation_id > installations.id

Ref: initial_invoices.subscription_id > customer_subscriptions.id
Ref: initial_invoices.activation_id > activations.id

Ref: initial_invoice_items.initial_invoice_id > initial_invoices.id