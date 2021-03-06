<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\CheckoutBranch;
use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use Phly\KeepAChangelog\Bump\ChangelogBump;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

use function sprintf;

class BumpAndCommitChangelogVersionViaKeepAChangelog implements BumpAndCommitChangelogVersion
{
    private const CHANGELOG_FILE = 'CHANGELOG.md';

    private const COMMIT_TEMPLATE = <<< 'COMMIT'
        Bumps changelog version to %s

        Updates the %s file to add a changelog entry for a new %s version.
        COMMIT;

    private ChangelogExists $changelogExists;
    private CheckoutBranch $checkoutBranch;
    private CommitFile $commitFile;
    private Push $push;
    private LoggerInterface $logger;

    public function __construct(
        ChangelogExists $changelogExists,
        CheckoutBranch $checkoutBranch,
        CommitFile $commitFile,
        Push $push,
        LoggerInterface $logger
    ) {
        $this->changelogExists = $changelogExists;
        $this->checkoutBranch  = $checkoutBranch;
        $this->commitFile      = $commitFile;
        $this->push            = $push;
        $this->logger          = $logger;
    }

    public function __invoke(
        string $bumpType,
        string $repositoryDirectory,
        SemVerVersion $version,
        BranchName $sourceBranch,
        SecretKeyId $keyId
    ): void {
        if (! ($this->changelogExists)($sourceBranch, $repositoryDirectory)) {
            // No changelog
            $this->logger->info('BumpAndCommitChangelogVersion: No CHANGELOG.md file detected');

            return;
        }

        ($this->checkoutBranch)($repositoryDirectory, $sourceBranch);

        $changelogFile = sprintf('%s/%s', $repositoryDirectory, self::CHANGELOG_FILE);
        $versionString = $version->fullReleaseName();
        $bumper        = new ChangelogBump($changelogFile);
        $newVersion    = $bumper->$bumpType($versionString);

        Assert::stringNotEmpty($newVersion);
        $bumper->updateChangelog($newVersion);

        $message = sprintf(self::COMMIT_TEMPLATE, $newVersion, self::CHANGELOG_FILE, $newVersion);
        Assert::notEmpty($message);

        ($this->commitFile)(
            $repositoryDirectory,
            $sourceBranch,
            self::CHANGELOG_FILE,
            $message,
            $keyId
        );

        ($this->push)($repositoryDirectory, $sourceBranch->name());

        $this->logger->info(sprintf(
            'BumpAndCommitChangelogVersion: bumped %s to version %s in branch %s',
            self::CHANGELOG_FILE,
            $newVersion,
            $sourceBranch->name()
        ));
    }
}
