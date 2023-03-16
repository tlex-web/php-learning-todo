
--
-- Database: `tasksdb`
--
CREATE DATABASE IF NOT EXISTS `tasksdb` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `tasksdb`;

-- --------------------------------------------------------

--
-- Table structure for table `tblimages`
--

CREATE TABLE `tblimages` (
                             `id` bigint(20) NOT NULL COMMENT 'Image ID Number - Primary Key',
                             `taskid` bigint(20) NOT NULL COMMENT 'Task ID Number - Foreign Key',
                             `title` varchar(255) NOT NULL COMMENT 'Image Title',
                             `filename` varchar(30) NOT NULL COMMENT 'Image Filename',
                             `mimetype` varchar(255) NOT NULL COMMENT 'File Mime Type - e.g. image/jpeg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Table to store task images';

-- --------------------------------------------------------

--
-- Table structure for table `tblsessions`
--

CREATE TABLE `tblsessions` (
                               `id` bigint(20) NOT NULL COMMENT 'Session ID',
                               `userid` bigint(20) NOT NULL COMMENT 'User ID',
                               `accesstoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Access Token',
                               `accesstokenexpiry` datetime NOT NULL COMMENT 'Access Token Expiry Date/Time',
                               `refreshtoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Refresh Token',
                               `refreshtokenexpiry` datetime NOT NULL COMMENT 'Refresh Token Expiry Date/Time'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tbltasks`
--

CREATE TABLE `tbltasks` (
                            `id` bigint(20) NOT NULL COMMENT 'Task ID Number - Primary Key',
                            `title` varchar(255) NOT NULL COMMENT 'Task Title',
                            `description` mediumtext COMMENT 'Task Description',
                            `deadline` datetime DEFAULT NULL COMMENT 'Task Deadline',
                            `completed` enum('N','Y') NOT NULL DEFAULT 'N' COMMENT 'Task Complete',
                            `userid` bigint(20) NOT NULL COMMENT 'User ID of owner of Task'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Table to store to do tasks';

-- --------------------------------------------------------

--
-- Table structure for table `tblusers`
--

CREATE TABLE `tblusers` (
                            `id` bigint(20) NOT NULL COMMENT 'User ID',
                            `fullname` varchar(255) NOT NULL COMMENT 'User Full Name',
                            `username` varchar(255) NOT NULL COMMENT 'Username',
                            `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Password',
                            `active` enum('N','Y') NOT NULL DEFAULT 'Y' COMMENT 'Is User Active',
                            `loginattempts` int(1) NOT NULL DEFAULT '0' COMMENT 'Attempts to Log in'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblimages`
--
ALTER TABLE `tblimages`
    ADD PRIMARY KEY (`id`),
    ADD UNIQUE KEY `filenamefortaskid` (`filename`,`taskid`),
    ADD KEY `imagetaskid_fk` (`taskid`);

--
-- Indexes for table `tblsessions`
--
ALTER TABLE `tblsessions`
    ADD PRIMARY KEY (`id`),
    ADD UNIQUE KEY `accesstoken` (`accesstoken`),
    ADD UNIQUE KEY `refreshtoken` (`refreshtoken`),
    ADD KEY `sessionuserid_fk` (`userid`);

--
-- Indexes for table `tbltasks`
--
ALTER TABLE `tbltasks`
    ADD PRIMARY KEY (`id`),
    ADD KEY `completed` (`completed`),
    ADD KEY `taskuserid_fk` (`userid`);

--
-- Indexes for table `tblusers`
--
ALTER TABLE `tblusers`
    ADD PRIMARY KEY (`id`),
    ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tblimages`
--
ALTER TABLE `tblimages`
    MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Image ID Number - Primary Key';

--
-- AUTO_INCREMENT for table `tblsessions`
--
ALTER TABLE `tblsessions`
    MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Session ID';

--
-- AUTO_INCREMENT for table `tbltasks`
--
ALTER TABLE `tbltasks`
    MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Task ID Number - Primary Key';

--
-- AUTO_INCREMENT for table `tblusers`
--
ALTER TABLE `tblusers`
    MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'User ID';

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblimages`
--
ALTER TABLE `tblimages`
    ADD CONSTRAINT `imagetaskid_fk` FOREIGN KEY (`taskid`) REFERENCES `tbltasks` (`id`);

--
-- Constraints for table `tblsessions`
--
ALTER TABLE `tblsessions`
    ADD CONSTRAINT `sessionuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`);

--
-- Constraints for table `tbltasks`
--
ALTER TABLE `tbltasks`
    ADD CONSTRAINT `taskuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`);