<?php

/**
 * @file
 * Contains \Drupal\Console\Command\MigrateDebugCommand.
 */

namespace Drupal\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

class YamlSplitCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('yaml:split')
            ->setDescription($this->trans('commands.yaml.split.description'))
            ->addArgument(
                'yaml-file',
                InputArgument::REQUIRED,
                $this->trans('commands.yaml.split.value.arguments.yaml-file')
            )
            ->addOption(
                'indent-level',
                false,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.yaml.split.options.indent-level')
            )
            ->addOption(
                'file-output-prefix',
                false,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.yaml.split.options.file-output-prefix')
            )
            ->addOption(
                'file-output-suffix',
                false,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.yaml.split.options.file-output-suffix')
            )
            ->addOption(
                'starting-key',
                false,
                InputOption::VALUE_REQUIRED,
                $this->trans('commands.yaml.split.options.starting-key')
            )
            ->addOption(
                'exclude-parents-key',
                false,
                InputOption::VALUE_NONE,
                $this->trans('commands.yaml.split.options.exclude-parents-key')
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $yaml = new Parser();
        $dumper = new Dumper();
        $messageHelper = $this->getMessageHelper();

        $yaml_file = $input->getArgument('yaml-file');
        $indent_level = $input->getOption('indent-level');
        $exclude_parents_key = $input->getOption('exclude-parents-key');
        $starting_key = $input->getOption('starting-key');
        $file_output_prefix = $input->getOption('file-output-prefix');
        $file_output_suffix = $input->getOption('file-output-suffix');

        if ($exclude_parents_key == 1 || $exclude_parents_key == 'TRUE') {
            $exclude_parents_key = true;
        } else {
            $exclude_parents_key = false;
        }

        try {
            $yaml_file_parsed = $yaml->parse(file_get_contents($yaml_file));

            if (empty($yaml_file_parsed)) {
                $output->writeln(
                    '[+] <info>'.sprintf(
                        $this->trans('commands.yaml.merge.messages.wrong-parse'),
                        $yaml_file_parsed
                    ).'</info>'
                );
            }
        } catch (\Exception $e) {
            $output->writeln('[+] <error>'.$this->trans('commands.yaml.merge.messages.error-parsing').': '.$e->getMessage().'</error>');

            return;
        }

        $nested_array = $this->getNestedArrayHelper();

        if ($starting_key) {
            $parents = explode(".", $starting_key);
            if ($nested_array->keyExists($yaml_file_parsed, $parents)) {
                $yaml_file_parsed = $nested_array->getValue($yaml_file_parsed,  $parents);
            } else {
                $output->writeln('[+] <error>'.$this->trans('commands.yaml.merge.messages.invalid-key').'</error>');
            }

            if ($indent_level == 0) {
                $yaml_split[$starting_key] = $yaml_file_parsed;
            }
        } else {
            // Set minimum level to split
            $indent_level = empty($indent_level)?1: $indent_level;

            $yaml_split = array();
            $key_flatten = '';
            $initial_level = 1;

            $nested_array->yaml_split_array($yaml_file_parsed, $yaml_split, $indent_level, $key_flatten, $initial_level, $exclude_parents_key);
        }

        $this->writeSplittedFile($yaml_split, $file_output_prefix, $file_output_suffix, $output);
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $validator_filename = function ($value) {
            if (!strlen(trim($value))) {
                throw new \Exception(' You must provide a valid file path.');
            }

            return $value;
        };

        $dialog = $this->getDialogHelper();

        // --yaml-left option
        $yaml_file = $input->getArgument('yaml-file');
        if (!$yaml_file) {
            $yaml_file = $dialog->askAndValidate(
                $output,
                $dialog->getQuestion($this->trans('commands.yaml.diff.questions.yaml-left'), ''),
                $validator_filename,
                false,
                null
            );
        }
        $input->setArgument('yaml-file', $yaml_file);
    }

    protected function writeSplittedFile($yaml_splitted, $file_output_prefix = '', $file_output_suffix = '', $output)
    {
        $dumper = new Dumper();

        $output->writeln(
            '[+] <info>'.
            $this->trans('commands.yaml.split.messages.generating-split')
            .'</info>'
        );

        foreach ($yaml_splitted as $key => $value) {
            if ($file_output_prefix) {
                $key = $file_output_prefix .  '.' . $key;
            }

            if ($file_output_suffix) {
                $key.= '.' . $file_output_suffix;
            }
            $filename = $key . '.yml';

            try {
                $yaml = $dumper->dump($value, 10);
            } catch (\Exception $e) {
                $output->writeln('[+] <error>'.$this->trans('commands.yaml.merge.messages.error-generating').': '.$e->getMessage().'</error>');

                return;
            }

            try {
                file_put_contents($filename, $yaml);
            } catch (\Exception $e) {
                $output->writeln('[+] <error>'.$this->trans('commands.yaml.merge.messages.error-writing').': '.$e->getMessage().'</error>');

                return;
            }

            $output->writeln(
                '    [-] <info>'.sprintf(
                    $this->trans('commands.yaml.split.messages.split-generated'),
                    $filename
                ).'</info>'
            );
        }
    }
}
