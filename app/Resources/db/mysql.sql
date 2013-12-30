CREATE  TABLE `property` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `address1` VARCHAR(1024) NOT NULL ,
  `address2` VARCHAR(1024) NULL ,
  `city` VARCHAR(128) NOT NULL ,
  `state` VARCHAR(45) NOT NULL ,
  `zip` VARCHAR(32) NOT NULL ,
  `price` INT(11) NULL ,
  `rent_zestimate` INT(11) NULL ,
  `rent_zestimate_low` INT(11) NULL ,
  `rent_zestimate_high` INT(11) NULL ,
  `zestimate_low` INT(11) NULL ,
  `zestimate_high` INT(11) NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB;

CREATE TABLE zipcodes
(
  zipcode VARCHAR(5) NOT NULL,
  last_imported DATETIME
);
CREATE UNIQUE INDEX unique_zipcode ON zipcodes ( zipcode );

ALTER TABLE `property` ADD COLUMN `zpid` VARCHAR(45) NOT NULL  AFTER `zestimate_high`
, ADD UNIQUE INDEX `zpid_UNIQUE` (`zpid` ASC) ;
