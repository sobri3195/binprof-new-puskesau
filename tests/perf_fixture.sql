-- MariaDB synthetic benchmark only. Run on a new disposable database after demo.sql.
INSERT INTO units(id,code,name,latitude,longitude)
SELECT seq+2,CONCAT('BENCH-',seq),CONCAT('DATA DEMO Satuan Ukur ',seq),
       -9 + MOD(seq,20)*0.5, 96 + MOD(seq,60)
FROM seq_1_to_98;
INSERT INTO personnel(id,unit_id,category_id,name,service_status)
SELECT seq,1+MOD(seq-1,100),1+MOD(seq,2),CONCAT('DATA DEMO Personel Ukur ',LPAD(seq,5,'0')),'active'
FROM seq_1_to_10000;
INSERT INTO education_histories(personnel_id,kind,type_id,institution,end_year,status)
SELECT p.id,IF(s.seq<=3,'dikum','dikmil'),IF(s.seq<=3,2,3),'DATA DEMO Kampus Ukur',2010+s.seq,'completed'
FROM personnel p CROSS JOIN seq_1_to_5 s;
